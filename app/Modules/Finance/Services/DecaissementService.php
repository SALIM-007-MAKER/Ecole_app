<?php

namespace App\Modules\Finance\Services;

use App\Modules\Finance\DTO\CategorieDepenseDTO;
use App\Modules\Finance\DTO\DecaissementDTO;
use App\Modules\Finance\DTO\FournisseurDTO;
use App\Modules\Finance\Events\DecaissementApproved;
use App\Modules\Finance\Events\DecaissementCancelled;
use App\Modules\Finance\Events\DecaissementCreated;
use App\Modules\Finance\Events\DecaissementRejected;
use App\Modules\Finance\Events\DecaissementValidated;
use App\Modules\Finance\Events\ExpenseValidated;
use App\Modules\Finance\Models\DecaissementModel;
use App\Modules\Finance\Repositories\DecaissementRepository;
use App\Services\AuditService;
use App\Services\UploadService;
use Core\EventDispatcher;
use Core\Tenant\BrandingService;
use Core\Tenant\SettingsService;
use Core\Tenant\TenantContext;

/**
 * Workflow : brouillon → soumis → valide → approuve → paye
 *                                        ↘ rejete
 *                     ↘ annule (depuis soumis / valide / approuve)
 *
 * Règles métier (Blueprint Finance V2 §12.3) :
 *  13. Tout décaissement > seuil (configurable, défaut 50 000) requiert
 *      l'approbation du directeur avant paiement.
 *  14. Aucune dépense ne peut être payée sans validation préalable.
 *  15. Numérotation séquentielle DEP-{AAAA}-{NNNN:04d}.
 *  16. Les justificatifs sont stockés via UploadService (type 'justification').
 *  — Un décaissement dont le montant dépasse le seuil doit avoir au moins un
 *    justificatif avant d'être approuvé.
 *  — Un décaissement payé en espèces impute la session de caisse active du payeur.
 */
class DecaissementService
{
    private DecaissementRepository $repo;
    private AuditService           $audit;
    private SettingsService        $settings;
    private UploadService          $upload;
    private CashRegisterService    $caisse;

    public function __construct()
    {
        $this->repo     = new DecaissementRepository();
        $this->audit    = new AuditService();
        $this->settings = SettingsService::make();
        $this->upload   = new UploadService();
        $this->caisse   = new CashRegisterService();
    }

    private function etabId(): int
    {
        return TenantContext::isSet() ? TenantContext::id() : BrandingService::forCurrentRequest()->etablissementId;
    }

    public function seuilApprobation(): float
    {
        return (float)$this->settings->get($this->etabId(), 'finance', 'seuil_approbation_decaissement', 50000.0);
    }

    // ----------------------------------------------------------------
    // Soumission
    // ----------------------------------------------------------------
    public function soumettre(DecaissementDTO $dto, int $userId): int
    {
        $errors = $dto->validate();
        if ($errors) {
            throw new \InvalidArgumentException(implode(' | ', $errors));
        }

        $annee  = (int)substr($dto->dateDepense, 0, 4);
        $numero = $this->repo->genererNumero('DEP', $annee);

        $decId = $this->repo->insert(array_merge($dto->toArray(), [
            'numero'    => $numero,
            'statut'    => 'soumis',
            'saisi_par' => $userId,
        ]));

        $this->audit->logCreate($userId, 'finance', 'decaissement', $decId, $dto->toArray());

        EventDispatcher::dispatch(new DecaissementCreated(
            decaissementId: $decId,
            numero:         $numero,
            libelle:        $dto->libelle,
            montant:        $dto->montant,
            saisiParId:     $userId,
        ));

        return $decId;
    }

    // ----------------------------------------------------------------
    // Validation (niveau 1)
    // ----------------------------------------------------------------
    public function valider(int $decId, int $userId): void
    {
        $dec = $this->requireDecaissement($decId);
        $this->assertTransition($dec->statut, 'valide');

        $this->repo->update($decId, [
            'statut'          => 'valide',
            'valide_par'      => $userId,
            'date_validation' => date('Y-m-d H:i:s'),
        ]);

        $this->audit->logUpdate($userId, 'finance', 'decaissement', $decId,
            ['statut' => $dec->statut], ['statut' => 'valide']);

        EventDispatcher::dispatch(new DecaissementValidated(
            decaissementId: $decId,
            numero:         $dec->numero,
            montant:        (float)$dec->montant,
            valideParId:    $userId,
        ));
    }

    // ----------------------------------------------------------------
    // Approbation (niveau 2 — directeur, requis si montant > seuil)
    // ----------------------------------------------------------------
    public function approuver(int $decId, int $userId): void
    {
        $dec = $this->requireDecaissement($decId);
        $this->assertTransition($dec->statut, 'approuve');

        if ((float)$dec->montant > $this->seuilApprobation()) {
            $nbJustificatifs = $this->repo->countJustificatifs($decId);
            if ($nbJustificatifs === 0) {
                throw new \RuntimeException(
                    "Ce décaissement dépasse le seuil d'approbation ({$this->seuilApprobation()} {$dec->devise}) "
                    . "et nécessite au moins un justificatif avant approbation."
                );
            }
        }

        $this->repo->update($decId, [
            'statut'           => 'approuve',
            'approuve_par'     => $userId,
            'date_approbation' => date('Y-m-d H:i:s'),
        ]);

        $this->audit->logUpdate($userId, 'finance', 'decaissement', $decId,
            ['statut' => $dec->statut], ['statut' => 'approuve']);

        EventDispatcher::dispatch(new DecaissementApproved(
            decaissementId: $decId,
            numero:         $dec->numero,
            montant:        (float)$dec->montant,
            approuveParId:  $userId,
        ));
    }

    // ----------------------------------------------------------------
    // Rejet
    // ----------------------------------------------------------------
    public function rejeter(int $decId, int $userId, string $motif): void
    {
        $dec = $this->requireDecaissement($decId);
        $this->assertTransition($dec->statut, 'rejete');

        if (trim($motif) === '') {
            throw new \InvalidArgumentException('Un motif de rejet est requis.');
        }

        $this->repo->update($decId, [
            'statut'      => 'rejete',
            'motif_rejet' => $motif,
        ]);

        $this->audit->logUpdate($userId, 'finance', 'decaissement', $decId,
            ['statut' => $dec->statut], ['statut' => 'rejete', 'motif' => $motif]);

        EventDispatcher::dispatch(new DecaissementRejected(
            decaissementId: $decId,
            numero:         $dec->numero,
            motif:          $motif,
            rejeteParId:    $userId,
        ));
    }

    // ----------------------------------------------------------------
    // Paiement — génère l'écriture comptable (ExpenseValidated) et,
    // si espèces, impute la session de caisse active du payeur.
    // ----------------------------------------------------------------
    public function payer(int $decId, int $userId, string $modePaiementCode, ?string $referenceExterne = null): void
    {
        $dec = $this->requireDecaissement($decId);

        $seuilOk = (float)$dec->montant <= $this->seuilApprobation();
        $peutPayer = $dec->statut === 'approuve' || ($dec->statut === 'valide' && $seuilOk);
        if (!$peutPayer) {
            throw new \RuntimeException(
                $dec->statut === 'valide'
                    ? "Ce décaissement dépasse le seuil d'approbation et doit être approuvé avant paiement."
                    : "Ce décaissement n'est pas payable dans son état actuel ({$dec->statut}). Il doit être validé (et approuvé si > seuil)."
            );
        }

        $mode = $this->repo->findModeByCode($modePaiementCode);
        if (!$mode) {
            throw new \InvalidArgumentException("Mode de paiement '{$modePaiementCode}' invalide.");
        }

        $updateData = [
            'statut'            => 'paye',
            'mode_paiement_id'  => $mode->id,
            'reference_externe' => $referenceExterne,
            'paye_par'          => $userId,
            'date_paiement'     => date('Y-m-d H:i:s'),
        ];

        // Imputation caisse si paiement en espèces
        if ($mode->categorie === 'caisse') {
            $session = $this->caisse->getSessionActive($userId);
            if ($session) {
                $updateData['caisse_session_id'] = $session->id;
            }
        }

        $this->repo->update($decId, $updateData);

        $this->audit->logUpdate($userId, 'finance', 'decaissement', $decId,
            ['statut' => $dec->statut], ['statut' => 'paye', 'mode_paiement' => $modePaiementCode]);

        if (($updateData['caisse_session_id'] ?? null) !== null) {
            $this->caisse->enregistrerMouvement(
                (int)$updateData['caisse_session_id'],
                new \App\Modules\Finance\DTO\CashMovementDTO(
                    type:      'decaissement',
                    sens:      'debit',
                    montant:   (float)$dec->montant,
                    libelle:   "Décaissement {$dec->numero} — {$dec->libelle}",
                    reference: $dec->numero,
                    source:    'decaissement',
                ),
                $userId
            );
        }

        // Génère l'écriture comptable en partie double (débit charge, crédit caisse/fournisseur)
        EventDispatcher::dispatch(new ExpenseValidated(
            depenseId:     $decId,
            numero:        $dec->numero,
            libelle:       $dec->libelle,
            montant:       (float)$dec->montant,
            categorie:     $dec->categorie_nom ?? 'Non catégorisé',
            modePaiement:  $modePaiementCode,
            fournisseurId: $dec->fournisseur_id !== null ? (int)$dec->fournisseur_id : null,
            validatedById: $userId,
        ));
    }

    // ----------------------------------------------------------------
    // Annulation sécurisée — réservée aux décaissements non payés
    // ----------------------------------------------------------------
    public function annuler(int $decId, int $userId, string $motif): void
    {
        $dec = $this->requireDecaissement($decId);

        if (!in_array($dec->statut, DecaissementModel::STATUTS_ANNULABLES, true)) {
            throw new \RuntimeException(
                "Ce décaissement ne peut plus être annulé dans son état actuel ({$dec->statut}). "
                . "Seuls les décaissements soumis, validés ou approuvés (mais pas encore payés) sont annulables."
            );
        }
        if (trim($motif) === '') {
            throw new \InvalidArgumentException('Un motif d\'annulation est requis.');
        }

        $ancienStatut = $dec->statut;
        $this->repo->update($decId, [
            'statut'           => 'annule',
            'annule_par'       => $userId,
            'date_annulation'  => date('Y-m-d H:i:s'),
            'motif_annulation' => $motif,
        ]);

        $this->audit->logUpdate($userId, 'finance', 'decaissement', $decId,
            ['statut' => $ancienStatut], ['statut' => 'annule', 'motif' => $motif]);

        EventDispatcher::dispatch(new DecaissementCancelled(
            decaissementId: $decId,
            numero:         $dec->numero,
            ancienStatut:   $ancienStatut,
            motif:          $motif,
            annuleParId:    $userId,
        ));
    }

    // ----------------------------------------------------------------
    // Justificatifs
    // ----------------------------------------------------------------
    public function ajouterJustificatif(int $decId, array $file, int $userId): int
    {
        $dec = $this->requireDecaissement($decId);

        $chemin = $this->upload->upload($file, 'justification', "dep_{$decId}_");

        $jusId = $this->repo->insertJustificatif([
            'decaissement_id' => $decId,
            'nom_fichier'     => $file['name'] ?? basename($chemin),
            'chemin'          => $chemin,
            'mime_type'       => mime_content_type(ROOT_PATH . '/' . $chemin) ?: 'application/octet-stream',
            'taille'          => filesize(ROOT_PATH . '/' . $chemin) ?: 0,
            'uploade_par'     => $userId,
        ]);

        $this->audit->logCreate($userId, 'finance', 'justificatif_decaissement', $jusId, [
            'decaissement_id' => $decId, 'nom_fichier' => $file['name'] ?? null,
        ]);

        return $jusId;
    }

    public function supprimerJustificatif(int $jusId, int $decId, int $userId): void
    {
        $jus = $this->repo->findJustificatif($jusId);
        if (!$jus || (int)$jus->decaissement_id !== $decId) {
            throw new \RuntimeException('Justificatif introuvable pour ce décaissement.');
        }
        $dec = $this->requireDecaissement($decId);
        if (!in_array($dec->statut, ['soumis', 'valide'], true)) {
            throw new \RuntimeException('Impossible de retirer un justificatif d\'un décaissement approuvé ou payé.');
        }

        $this->upload->delete($jus->chemin);
        $this->repo->deleteJustificatif($jusId);

        $this->audit->log($userId, 'delete', 'finance', 'justificatif_decaissement', $jusId, $jus->chemin ? ['chemin' => $jus->chemin] : null, null);
    }

    // ----------------------------------------------------------------
    // Catégories de dépenses
    // ----------------------------------------------------------------
    public function creerCategorie(CategorieDepenseDTO $dto, int $userId): int
    {
        $errors = $dto->validate();
        if ($errors) {
            throw new \InvalidArgumentException(implode(' | ', $errors));
        }
        if ($this->repo->categorieCodeExists($dto->code)) {
            throw new \RuntimeException("Une catégorie avec le code « {$dto->code} » existe déjà.");
        }
        $id = $this->repo->insertCategorie($dto->toArray());
        $this->audit->logCreate($userId, 'finance', 'categorie_depense', $id, $dto->toArray());
        return $id;
    }

    public function modifierCategorie(int $id, CategorieDepenseDTO $dto, int $userId): void
    {
        $errors = $dto->validate();
        if ($errors) {
            throw new \InvalidArgumentException(implode(' | ', $errors));
        }
        if ($this->repo->categorieCodeExists($dto->code, $id)) {
            throw new \RuntimeException("Une catégorie avec le code « {$dto->code} » existe déjà.");
        }
        $avant = $this->repo->findCategorie($id);
        $this->repo->updateCategorie($id, $dto->toArray());
        $this->audit->logUpdate($userId, 'finance', 'categorie_depense', $id, (array)$avant, $dto->toArray());
    }

    public function toggleCategorie(int $id, int $userId): void
    {
        $cat = $this->repo->findCategorie($id);
        if (!$cat) {
            throw new \RuntimeException('Catégorie introuvable.');
        }
        if ($cat->actif && $this->repo->countUsageCategorie($id) > 0) {
            // Désactivation autorisée même si utilisée (catégorie historique) —
            // seule la suppression physique est bloquée par la FK ON DELETE SET NULL.
        }
        $nouveauStatut = $cat->actif ? 0 : 1;
        $this->repo->updateCategorie($id, ['actif' => $nouveauStatut]);
        $this->audit->logUpdate($userId, 'finance', 'categorie_depense', $id,
            ['actif' => $cat->actif], ['actif' => $nouveauStatut]);
    }

    // ----------------------------------------------------------------
    // Fournisseurs
    // ----------------------------------------------------------------
    public function creerFournisseur(FournisseurDTO $dto, int $userId): int
    {
        $errors = $dto->validate();
        if ($errors) {
            throw new \InvalidArgumentException(implode(' | ', $errors));
        }
        if ($this->repo->fournisseurCodeExists($dto->code)) {
            throw new \RuntimeException("Un fournisseur avec le code « {$dto->code} » existe déjà.");
        }
        $id = $this->repo->insertFournisseur(array_merge($dto->toArray(), ['created_by' => $userId]));
        $this->audit->logCreate($userId, 'finance', 'fournisseur', $id, $dto->toArray());
        return $id;
    }

    public function modifierFournisseur(int $id, FournisseurDTO $dto, int $userId): void
    {
        $errors = $dto->validate();
        if ($errors) {
            throw new \InvalidArgumentException(implode(' | ', $errors));
        }
        if ($this->repo->fournisseurCodeExists($dto->code, $id)) {
            throw new \RuntimeException("Un fournisseur avec le code « {$dto->code} » existe déjà.");
        }
        $avant = $this->repo->findFournisseur($id);
        $this->repo->updateFournisseur($id, $dto->toArray());
        $this->audit->logUpdate($userId, 'finance', 'fournisseur', $id, (array)$avant, $dto->toArray());
    }

    public function toggleFournisseur(int $id, int $userId): void
    {
        $f = $this->repo->findFournisseur($id);
        if (!$f) {
            throw new \RuntimeException('Fournisseur introuvable.');
        }
        $nouveauStatut = $f->actif ? 0 : 1;
        $this->repo->updateFournisseur($id, ['actif' => $nouveauStatut]);
        $this->audit->logUpdate($userId, 'finance', 'fournisseur', $id,
            ['actif' => $f->actif], ['actif' => $nouveauStatut]);
    }

    // ----------------------------------------------------------------
    // Privé
    // ----------------------------------------------------------------
    private function requireDecaissement(int $id): object
    {
        $dec = $this->repo->findWithDetails($id);
        if (!$dec) {
            throw new \RuntimeException("Décaissement #{$id} introuvable.");
        }
        $model = new DecaissementModel();
        foreach ((array)$dec as $k => $v) {
            $model->{$k} = $v;
        }
        return $model;
    }

    private function assertTransition(string $statutActuel, string $statutCible): void
    {
        $autorises = DecaissementModel::TRANSITIONS[$statutActuel] ?? [];
        if (!in_array($statutCible, $autorises, true)) {
            throw new \RuntimeException(
                "Transition invalide : impossible de passer de « {$statutActuel} » à « {$statutCible} »."
            );
        }
    }
}

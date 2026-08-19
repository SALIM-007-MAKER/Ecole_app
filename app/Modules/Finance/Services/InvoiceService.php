<?php

namespace App\Modules\Finance\Services;

use App\Modules\Finance\Contracts\FacturationInterface;
use App\Modules\Finance\DTO\InvoiceDTO;
use App\Modules\Finance\DTO\LigneFactureDTO;
use App\Modules\Finance\DTO\RemiseDTO;
use App\Modules\Finance\Events\InvoiceCreated;
use App\Modules\Finance\Events\InvoiceGenerated;
use App\Modules\Finance\Events\InvoiceCancelled;
use App\Modules\Finance\Events\InvoiceUpdated;
use App\Modules\Finance\Events\InvoiceArchived;
use App\Modules\Finance\Repositories\InvoiceRepository;
use App\Services\AuditService;
use Core\EventDispatcher;
use Core\Tenant\BrandingService;
use Core\Tenant\SettingsService;
use Core\Tenant\TenantContext;

class InvoiceService implements FacturationInterface
{
    private InvoiceRepository $repo;
    private AuditService      $audit;
    private SettingsService   $settings;

    public function __construct()
    {
        $this->repo     = new InvoiceRepository();
        $this->audit    = new AuditService();
        $this->settings = SettingsService::make();
    }

    /** Préfixe de numérotation des factures, configurable par établissement (voir /parametres/documents). */
    private function prefixeFacture(): string
    {
        $etabId = TenantContext::isSet() ? TenantContext::id() : BrandingService::forCurrentRequest()->etablissementId;
        return $this->settings->get($etabId, 'documents', 'prefixe_facture', 'FCT');
    }

    // ----------------------------------------------------------------
    // Création d'une facture individuelle
    // ----------------------------------------------------------------
    public function creerFacture(InvoiceDTO $dto, array $lignes, int $userId): int
    {
        $errors = $dto->validate();
        if ($errors) {
            throw new \InvalidArgumentException(implode(' | ', $errors));
        }
        if (empty($lignes)) {
            throw new \InvalidArgumentException('Une facture doit avoir au moins une ligne.');
        }

        $pdo = $this->repo->getPdo();
        $pdo->beginTransaction();
        try {
            $annee  = (int)substr(date('Y'), 0, 4);
            $numero = $this->repo->genererNumero($this->prefixeFacture(), $annee);

            $factureId = $this->repo->insert([
                'numero'         => $numero,
                'eleve_id'       => $dto->eleveId,
                'annee_scolaire' => $dto->anneeScolaire,
                'date_emission'  => date('Y-m-d'),
                'date_echeance'  => $dto->dateEcheance,
                'montant_ht'     => 0.00,
                'montant_remise' => 0.00,
                'montant_penalite' => 0.00,
                'montant_total'  => 0.00,
                'montant_paye'   => 0.00,
                'statut'         => 'brouillon',
                'note'           => $dto->note,
                'emise_par'      => $userId,
            ]);

            foreach ($lignes as $idx => $ligne) {
                $ligneDto = $ligne instanceof LigneFactureDTO
                    ? $ligne
                    : LigneFactureDTO::fromRequest($ligne);
                $ligneErrors = $ligneDto->validate();
                if ($ligneErrors) {
                    throw new \InvalidArgumentException(
                        "Ligne " . ($idx + 1) . " : " . implode(' | ', $ligneErrors)
                    );
                }
                $this->repo->insertLigne(array_merge(
                    $ligneDto->toArray(),
                    ['facture_id' => $factureId, 'ordre' => $idx + 1]
                ));
            }

            $this->recalculerMontants($factureId);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        $facture = $this->repo->findWithDetails($factureId);
        $this->audit->logCreate($userId, 'finance', 'facture', $factureId, (array)$facture);

        EventDispatcher::dispatch(new InvoiceCreated(
            factureId:     $factureId,
            numero:        $facture->numero,
            eleveId:       $dto->eleveId,
            anneeScolaire: $dto->anneeScolaire,
            montantTotal:  (float)$facture->montant_total,
            createdById:   $userId,
        ));

        return $factureId;
    }

    // ----------------------------------------------------------------
    // Ajout / suppression de ligne (facture brouillon uniquement)
    // ----------------------------------------------------------------
    public function ajouterLigne(int $factureId, LigneFactureDTO $ligne, int $userId): void
    {
        $facture = $this->requireFacture($factureId);
        if (!$facture->estEditable()) {
            throw new \RuntimeException("Seules les factures en brouillon peuvent être modifiées.");
        }
        $errors = $ligne->validate();
        if ($errors) {
            throw new \InvalidArgumentException(implode(' | ', $errors));
        }
        $count = count($this->repo->getLignes($factureId));
        $this->repo->insertLigne(array_merge(
            $ligne->toArray(),
            ['facture_id' => $factureId, 'ordre' => $count + 1]
        ));
        $this->recalculerMontants($factureId);
        $this->audit->logUpdate($userId, 'finance', 'facture', $factureId, [], ['ligne_ajoutee' => $ligne->libelle]);
    }

    public function supprimerLigne(int $factureId, int $ligneId, int $userId): void
    {
        $facture = $this->requireFacture($factureId);
        if (!$facture->estEditable()) {
            throw new \RuntimeException("Seules les factures en brouillon peuvent être modifiées.");
        }
        $lignes = $this->repo->getLignes($factureId);
        if (count($lignes) <= 1) {
            throw new \RuntimeException("Impossible de supprimer la dernière ligne d'une facture.");
        }
        $deleted = $this->repo->deleteLigne($ligneId, $factureId);
        if (!$deleted) {
            throw new \RuntimeException("Ligne introuvable.");
        }
        $this->recalculerMontants($factureId);
    }

    // ----------------------------------------------------------------
    // Remises — s'appliquent avant les pénalités (business rule #5)
    // ----------------------------------------------------------------
    public function appliquerRemise(int $factureId, RemiseDTO $remise, int $userId): void
    {
        $facture = $this->requireFacture($factureId);
        if (!in_array($facture->statut, ['brouillon', 'emise'], true)) {
            throw new \RuntimeException("Les remises ne peuvent être appliquées qu'à une facture brouillon ou émise.");
        }
        $errors = $remise->validate();
        if ($errors) {
            throw new \InvalidArgumentException(implode(' | ', $errors));
        }

        $montantHt = $this->repo->sumLignes($factureId);
        $montantCalcule = $remise->calculerMontant($montantHt);

        $avant = ['montant_remise' => $facture->montant_remise];

        $this->repo->insertRemise([
            'facture_id'      => $factureId,
            'libelle'         => $remise->libelle,
            'type_remise'     => $remise->typeRemise,
            'valeur'          => $remise->valeur,
            'montant_calcule' => $montantCalcule,
            'regle_exo_id'    => $remise->regleExoId,
            'justificatif'    => $remise->justificatif,
            'accordee_par'    => $userId,
        ]);

        $this->recalculerMontants($factureId);

        $factureMaj = $this->repo->findWithDetails($factureId);
        $this->audit->logUpdate($userId, 'finance', 'facture', $factureId,
            $avant,
            ['montant_remise' => $factureMaj->montant_remise]
        );
        EventDispatcher::dispatch(new InvoiceUpdated(
            factureId:     $factureId,
            numero:        $facture->numero,
            changedFields: ['remise_appliquee' => $montantCalcule],
            updatedById:   $userId,
        ));
    }

    // ----------------------------------------------------------------
    // Pénalités de retard
    // ----------------------------------------------------------------
    public function appliquerPenalite(int $factureId, float $montant, string $motif, int $userId): void
    {
        $facture = $this->requireFacture($factureId);
        if (!in_array($facture->statut, ['emise', 'partiellement_payee', 'en_retard'], true)) {
            throw new \RuntimeException("Les pénalités ne s'appliquent qu'aux factures émises non payées.");
        }
        if ($montant <= 0) {
            throw new \InvalidArgumentException("Le montant de la pénalité doit être positif.");
        }

        $nbJours = null;
        if ($facture->date_echeance) {
            $diff    = (new \DateTime())->diff(new \DateTime($facture->date_echeance));
            $nbJours = max(0, $diff->invert ? $diff->days : 0);
        }

        $this->repo->insertPenalite([
            'facture_id'   => $factureId,
            'montant'      => $montant,
            'motif'        => $motif,
            'nb_jours'     => $nbJours,
            'applique_par' => $userId,
        ]);

        $this->recalculerMontants($factureId);
        $this->audit->logUpdate($userId, 'finance', 'facture', $factureId,
            ['montant_penalite' => $facture->montant_penalite],
            ['penalite_ajoutee' => $montant]
        );
    }

    // ----------------------------------------------------------------
    // Échéancier de paiement
    // ----------------------------------------------------------------
    public function creerEcheancier(int $factureId, array $echeances, int $userId): void
    {
        $facture = $this->requireFacture($factureId);
        if (!in_array($facture->statut, ['brouillon', 'emise'], true)) {
            throw new \RuntimeException("Impossible de créer un échéancier sur cette facture.");
        }
        if (empty($echeances)) {
            throw new \InvalidArgumentException("L'échéancier doit contenir au moins une échéance.");
        }

        $pdo = $this->repo->getPdo();
        $pdo->beginTransaction();
        try {
            $echId = $this->repo->insertEcheancier($factureId, count($echeances));
            foreach ($echeances as $i => $ech) {
                $this->repo->insertEcheance([
                    'echeancier_id' => $echId,
                    'numero_ordre'  => $i + 1,
                    'date_echeance' => $ech['date'],
                    'montant_du'    => (float)$ech['montant'],
                    'statut'        => 'en_attente',
                ]);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        $this->audit->logUpdate($userId, 'finance', 'facture', $factureId, [], ['echeancier_cree' => count($echeances)]);
    }

    // ----------------------------------------------------------------
    // Émission — brouillon → emise (business rule #4)
    // ----------------------------------------------------------------
    public function emettre(int $factureId, int $userId): void
    {
        $facture = $this->requireFacture($factureId);
        if ($facture->statut !== 'brouillon') {
            throw new \RuntimeException("Seules les factures en brouillon peuvent être émises.");
        }
        $lignes = $this->repo->getLignes($factureId);
        if (empty($lignes)) {
            throw new \RuntimeException("Impossible d'émettre une facture sans ligne.");
        }
        if ((float)$facture->montant_total <= 0) {
            throw new \RuntimeException("Impossible d'émettre une facture dont le montant est nul.");
        }

        $this->repo->update($factureId, [
            'statut'    => 'emise',
            'emise_par' => $userId,
        ]);

        $this->audit->logUpdate($userId, 'finance', 'facture', $factureId,
            ['statut' => 'brouillon'],
            ['statut' => 'emise']
        );
        $factureMaj = $this->repo->findWithDetails($factureId);
        EventDispatcher::dispatch(new InvoiceUpdated(
            factureId:     $factureId,
            numero:        $factureMaj->numero,
            changedFields: ['statut' => 'emise'],
            updatedById:   $userId,
        ));
    }

    // ----------------------------------------------------------------
    // Annulation (business rules #7, #8, #9)
    // ----------------------------------------------------------------
    public function annuler(int $factureId, string $motif, int $userId): void
    {
        $facture = $this->requireFacture($factureId);

        if ($facture->statut === 'annulee') {
            throw new \RuntimeException("Cette facture est déjà annulée.");
        }
        if ($facture->statut === 'brouillon') {
            throw new \RuntimeException("Supprimez la facture plutôt que de l'annuler (elle est en brouillon).");
        }
        if (!in_array($facture->statut, \App\Modules\Finance\Models\FactureModel::STATUTS_ANNULABLES, true)) {
            throw new \RuntimeException("Cette facture ne peut pas être annulée dans son état actuel.");
        }

        $pdo = $this->repo->getPdo();
        $pdo->beginTransaction();
        try {
            $avoirId = null;

            // Si des paiements existent → créer un avoir (business rule #9)
            if ((float)$facture->montant_paye > 0) {
                $numAvoir = $this->repo->genererNumero('AVO', (int)date('Y'));
                $avoirId  = $this->repo->insertAvoir([
                    'numero'             => $numAvoir,
                    'facture_origine_id' => $factureId,
                    'eleve_id'           => $facture->eleve_id,
                    'annee_scolaire'     => $facture->annee_scolaire,
                    'montant'            => $facture->montant_paye,
                    'motif'              => "Annulation facture {$facture->numero} — {$motif}",
                    'statut'             => 'emis',
                    'emis_par'           => $userId,
                ]);
            }

            $this->repo->update($factureId, [
                'statut'           => 'annulee',
                'annulee_par'      => $userId,
                'date_annulation'  => date('Y-m-d'),
                'motif_annulation' => $motif,
                'avoir_id'         => $avoirId,
            ]);

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        $this->audit->logUpdate($userId, 'finance', 'facture', $factureId,
            ['statut' => $facture->statut],
            ['statut' => 'annulee', 'motif' => $motif, 'avoir_id' => $avoirId]
        );
        EventDispatcher::dispatch(new InvoiceCancelled(
            factureId:    $factureId,
            numero:       $facture->numero,
            eleveId:      (int)$facture->eleve_id,
            montantTotal: (float)$facture->montant_total,
            montantPaye:  (float)$facture->montant_paye,
            motif:        $motif,
            avoirId:      $avoirId,
            cancelledById: $userId,
        ));
    }

    // ----------------------------------------------------------------
    // Archivage (business rule #8 : la facture reste consultable)
    // ----------------------------------------------------------------
    public function archiver(int $factureId, int $userId): void
    {
        $facture = $this->requireFacture($factureId);
        if (!in_array($facture->statut, ['payee', 'annulee'], true)) {
            throw new \RuntimeException("Seules les factures payées ou annulées peuvent être archivées.");
        }

        $this->repo->update($factureId, ['statut' => 'archive']);
        $this->audit->logUpdate($userId, 'finance', 'facture', $factureId,
            ['statut' => $facture->statut],
            ['statut' => 'archive']
        );
        EventDispatcher::dispatch(new InvoiceArchived(
            factureId:    $factureId,
            numero:       $facture->numero,
            ancienStatut: $facture->statut,
            archivedById: $userId,
        ));
    }

    // ----------------------------------------------------------------
    // Suppression (business rule #6 : uniquement brouillon)
    // ----------------------------------------------------------------
    public function supprimer(int $factureId, int $userId): void
    {
        $facture = $this->requireFacture($factureId);
        if ($facture->statut !== 'brouillon') {
            throw new \RuntimeException("Seules les factures en brouillon peuvent être supprimées.");
        }
        // CASCADE supprime les lignes/remises/pénalités/échéanciers
        $this->repo->update($factureId, ['statut' => 'annulee']);
        $pdo = $this->repo->getPdo();
        $pdo->exec("DELETE FROM `finance_factures` WHERE `id` = {$factureId}");
        $this->audit->logDelete($userId, 'finance', 'facture', $factureId, (array)$facture);
    }

    // ----------------------------------------------------------------
    // Modification (brouillon seulement — note & date_echeance)
    // ----------------------------------------------------------------
    public function modifier(int $factureId, array $data, int $userId): void
    {
        $facture = $this->requireFacture($factureId);
        if (!$facture->estEditable()) {
            throw new \RuntimeException("Seules les factures en brouillon peuvent être modifiées.");
        }
        $champs = [];
        if (isset($data['date_echeance'])) {
            $champs['date_echeance'] = $data['date_echeance'] ?: null;
        }
        if (isset($data['note'])) {
            $champs['note'] = trim($data['note']);
        }
        if (empty($champs)) {
            return;
        }
        $this->repo->update($factureId, $champs);
        $this->audit->logUpdate($userId, 'finance', 'facture', $factureId, [], $champs);
        EventDispatcher::dispatch(new InvoiceUpdated(
            factureId:     $factureId,
            numero:        $facture->numero,
            changedFields: $champs,
            updatedById:   $userId,
        ));
    }

    // ----------------------------------------------------------------
    // Génération pour une classe entière
    // ----------------------------------------------------------------
    public function genererPourClasse(int $classeId, array $fraisTypeIds, string $annee, int $userId): array
    {
        if (empty($fraisTypeIds)) {
            throw new \InvalidArgumentException("Sélectionnez au moins un type de frais.");
        }
        $eleves      = $this->repo->getElevesByClasse($classeId, $annee);
        $fraisTypes  = $this->repo->getFraisTypesForMasse($fraisTypeIds);
        return $this->genererBatch($eleves, $fraisTypes, $annee, "Classe ID:{$classeId}", $userId);
    }

    public function genererMasse(string $annee, ?string $niveau, array $fraisTypeIds, int $userId): array
    {
        if (empty($fraisTypeIds)) {
            throw new \InvalidArgumentException("Sélectionnez au moins un type de frais.");
        }
        $eleves = $niveau
            ? $this->repo->getElevesByNiveau($niveau, $annee)
            : $this->repo->getElevesByNiveau('', $annee);
        $fraisTypes = $this->repo->getFraisTypesForMasse($fraisTypeIds);
        return $this->genererBatch($eleves, $fraisTypes, $annee, "Niveau:{$niveau}", $userId);
    }

    // ----------------------------------------------------------------
    // Internals
    // ----------------------------------------------------------------
    private function genererBatch(array $eleves, array $fraisTypes, string $annee, string $contexte, int $userId): array
    {
        $resultats = ['crees' => 0, 'ignores' => 0, 'erreurs' => []];
        $fraisIds  = array_column($fraisTypes, 'id');

        foreach ($eleves as $eleve) {
            // Ignorer si une facture existe déjà pour ces frais (business rule #1 génération masse)
            if ($this->repo->factureExiste((int)$eleve->eleve_id, $annee, $fraisIds)) {
                $resultats['ignores']++;
                continue;
            }
            try {
                $dto    = new InvoiceDTO(eleveId: (int)$eleve->eleve_id, anneeScolaire: $annee);
                $lignes = array_map(fn($ft) => new LigneFactureDTO(
                    libelle:         $ft->nom,
                    quantite:        1.0,
                    montantUnitaire: (float)$ft->montant_defaut,
                    fraisTypeId:     (int)$ft->id,
                ), $fraisTypes);
                $this->creerFacture($dto, $lignes, $userId);
                $resultats['crees']++;
            } catch (\Throwable $e) {
                $resultats['erreurs'][] = "Élève #{$eleve->eleve_id} ({$eleve->nom}) : " . $e->getMessage();
            }
        }

        if ($resultats['crees'] > 0) {
            EventDispatcher::dispatch(new InvoiceGenerated(
                nbFactures:    $resultats['crees'],
                anneeScolaire: $annee,
                contexte:      $contexte,
                generatedById: $userId,
            ));
        }
        return $resultats;
    }

    private function recalculerMontants(int $factureId): void
    {
        $ht        = $this->repo->sumLignes($factureId);
        $remise    = $this->repo->sumRemises($factureId);
        $penalite  = $this->repo->sumPenalites($factureId);
        $total     = max(0.0, round($ht - $remise + $penalite, 2));

        $this->repo->update($factureId, [
            'montant_ht'       => round($ht, 2),
            'montant_remise'   => round($remise, 2),
            'montant_penalite' => round($penalite, 2),
            'montant_total'    => $total,
        ]);
    }

    private function requireFacture(int $id): object
    {
        $f = $this->repo->findWithDetails($id);
        if (!$f) {
            throw new \RuntimeException("Facture #{$id} introuvable.");
        }
        // Hydrater les helpers de FactureModel
        $model = new \App\Modules\Finance\Models\FactureModel();
        foreach ((array)$f as $k => $v) {
            $model->{$k} = $v;
        }
        return $model;
    }
}

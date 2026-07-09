<?php

namespace App\Modules\Finance\Services;

use App\Modules\Finance\DTO\PaymentDTO;
use App\Modules\Finance\DTO\RefundDTO;
use App\Modules\Finance\Events\PaymentInitiated;
use App\Modules\Finance\Events\PaymentCompleted;
use App\Modules\Finance\Events\PaymentPartial;
use App\Modules\Finance\Events\PaymentRefunded;
use App\Modules\Finance\Events\PaymentCancelled;
use App\Modules\Finance\Events\ReceiptGenerated;
use App\Modules\Finance\Repositories\PaymentRepository;
use App\Services\AuditService;
use Core\EventDispatcher;

class PaymentService
{
    private PaymentRepository $repo;
    private AuditService      $audit;

    public function __construct()
    {
        $this->repo  = new PaymentRepository();
        $this->audit = new AuditService();
    }

    // ----------------------------------------------------------------
    // Point d'entrée principal — enregistre ET complète en une passe
    // Workflow : initie → complete atomiquement (cas espèces / simple)
    // ----------------------------------------------------------------
    public function enregistrer(PaymentDTO $dto, int $userId): int
    {
        $errors = $dto->validate();
        if ($errors) {
            throw new \InvalidArgumentException(implode(' | ', $errors));
        }

        $facture = $this->requireFacture($dto->factureId);
        $this->validerPayable($facture);

        $pdo = $this->repo->getPdo();
        $pdo->beginTransaction();
        try {
            // Calcul du montant applicable (rule: montant_paye ≤ montant_total)
            $restant        = max(0.0, (float)$facture->montant_total - (float)$facture->montant_paye);
            $montantApplique = min($dto->montant, $restant);
            $excedent        = round($dto->montant - $montantApplique, 2);

            $modeId = $this->resolverMode($dto->modePaiement);

            // Vérification avoir (si mode = AVOIR)
            if ($dto->modePaiement === 'AVOIR') {
                $this->validerAvoir($dto->avoirId, $montantApplique);
            }

            $numero     = $this->repo->genererNumero('PAI', (int)date('Y'));
            $paiementId = $this->repo->insert([
                'numero'            => $numero,
                'facture_id'        => $dto->factureId,
                'mode_paiement_id'  => $modeId,
                'montant'           => $dto->montant,
                'montant_applique'  => $montantApplique,
                'reference_externe' => $dto->referenceExterne,
                'date_paiement'     => $dto->datePaiement,
                'statut'            => 'complete',
                'echeance_id'       => $dto->echeanceId,
                'avoir_id'          => $dto->avoirId,
                'note'              => $dto->note,
                'encaisse_par'      => $userId,
                'valide_par'        => $userId,
                'date_validation'   => date('Y-m-d'),
            ]);

            // Mise à jour de l'avoir utilisé
            if ($dto->modePaiement === 'AVOIR' && $dto->avoirId) {
                $this->repo->updateAvoir($dto->avoirId, [
                    'statut'     => 'utilise',
                    'utilise_sur' => $dto->factureId,
                ]);
            }

            // Mise à jour échéance ciblée
            if ($dto->echeanceId) {
                $this->mettreAJourEcheance($dto->echeanceId, $montantApplique);
            }

            // Mise à jour facture
            $nouveauStatut = $this->calculerStatutFacture($facture, $montantApplique);
            $this->repo->updateFacture($dto->factureId, [
                'montant_paye' => round((float)$facture->montant_paye + $montantApplique, 2),
                'statut'       => $nouveauStatut,
            ]);

            // Trop-perçu
            if ($excedent > 0.01) {
                $this->repo->insertTropPercu([
                    'paiement_id'      => $paiementId,
                    'facture_id'       => $dto->factureId,
                    'eleve_id'         => (int)$facture->eleve_id,
                    'montant_excedent' => $excedent,
                    'statut'           => 'en_attente',
                    'note'             => "Trop-perçu sur paiement {$numero}",
                ]);
            }

            // Reçu
            $recuId = $this->creerRecu($paiementId, $dto->factureId, (int)$facture->eleve_id, $montantApplique, $userId);

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        // Audit
        $this->audit->logCreate($userId, 'finance', 'paiement', $paiementId);

        // Events
        $estPartiel = $nouveauStatut === 'partiellement_payee';
        if ($estPartiel) {
            $restantApres = round((float)$facture->montant_total - (float)$facture->montant_paye - $montantApplique, 2);
            EventDispatcher::dispatch(new PaymentPartial(
                paiementId:      $paiementId,
                numero:          $numero,
                factureId:       $dto->factureId,
                montantApplique: $montantApplique,
                montantRestant:  $restantApres,
                completedById:   $userId,
            ));
        }
        EventDispatcher::dispatch(new PaymentCompleted(
            paiementId:           $paiementId,
            numero:               $numero,
            factureId:            $dto->factureId,
            eleveId:              (int)$facture->eleve_id,
            montant:              $dto->montant,
            montantApplique:      $montantApplique,
            modePaiement:         $dto->modePaiement,
            nouveauStatutFacture: $nouveauStatut,
            completedById:        $userId,
        ));

        return $paiementId;
    }

    // ----------------------------------------------------------------
    // Workflow multi-étapes — Étape 1 : initier
    // ----------------------------------------------------------------
    public function initierPaiement(PaymentDTO $dto, int $userId): int
    {
        $errors = $dto->validate();
        if ($errors) {
            throw new \InvalidArgumentException(implode(' | ', $errors));
        }
        $facture = $this->requireFacture($dto->factureId);
        $this->validerPayable($facture);

        $modeId = $this->resolverMode($dto->modePaiement);
        $numero = $this->repo->genererNumero('PAI', (int)date('Y'));

        $restant = max(0.0, (float)$facture->montant_total - (float)$facture->montant_paye);
        $montantApplique = min($dto->montant, $restant);

        $paiementId = $this->repo->insert([
            'numero'            => $numero,
            'facture_id'        => $dto->factureId,
            'mode_paiement_id'  => $modeId,
            'montant'           => $dto->montant,
            'montant_applique'  => $montantApplique,
            'reference_externe' => $dto->referenceExterne,
            'date_paiement'     => $dto->datePaiement,
            'statut'            => 'initie',
            'echeance_id'       => $dto->echeanceId,
            'avoir_id'          => $dto->avoirId,
            'note'              => $dto->note,
            'encaisse_par'      => $userId,
        ]);

        $this->audit->logCreate($userId, 'finance', 'paiement', $paiementId);

        EventDispatcher::dispatch(new PaymentInitiated(
            paiementId:    $paiementId,
            numero:        $numero,
            factureId:     $dto->factureId,
            montant:       $dto->montant,
            modePaiement:  $dto->modePaiement,
            initiatedById: $userId,
        ));

        return $paiementId;
    }

    // ----------------------------------------------------------------
    // Workflow multi-étapes — Étape 2 : valider
    // ----------------------------------------------------------------
    public function valider(int $paiementId, int $userId): void
    {
        $paiement = $this->requirePaiement($paiementId);
        if ($paiement->statut !== 'initie') {
            throw new \RuntimeException("Seuls les paiements initiés peuvent être validés.");
        }

        $this->repo->update($paiementId, [
            'statut'         => 'valide',
            'valide_par'     => $userId,
            'date_validation' => date('Y-m-d'),
        ]);

        $this->audit->logUpdate($userId, 'finance', 'paiement', $paiementId,
            ['statut' => 'initie'], ['statut' => 'valide']
        );
    }

    // ----------------------------------------------------------------
    // Workflow multi-étapes — Étape 3 : compléter
    // ----------------------------------------------------------------
    public function completer(int $paiementId, int $userId): void
    {
        $paiement = $this->requirePaiement($paiementId);
        if (!in_array($paiement->statut, ['initie', 'valide'], true)) {
            throw new \RuntimeException("Ce paiement ne peut pas être complété dans son état actuel.");
        }

        $facture = $this->requireFacture((int)$paiement->facture_id);
        $montantApplique = (float)$paiement->montant_applique;
        $excedent = round((float)$paiement->montant - $montantApplique, 2);

        $pdo = $this->repo->getPdo();
        $pdo->beginTransaction();
        try {
            $this->repo->update($paiementId, ['statut' => 'complete']);

            if ($paiement->echeance_id) {
                $this->mettreAJourEcheance((int)$paiement->echeance_id, $montantApplique);
            }

            $nouveauStatut = $this->calculerStatutFacture($facture, $montantApplique);
            $this->repo->updateFacture((int)$paiement->facture_id, [
                'montant_paye' => round((float)$facture->montant_paye + $montantApplique, 2),
                'statut'       => $nouveauStatut,
            ]);

            if ($excedent > 0.01) {
                $this->repo->insertTropPercu([
                    'paiement_id'      => $paiementId,
                    'facture_id'       => (int)$paiement->facture_id,
                    'eleve_id'         => (int)$facture->eleve_id,
                    'montant_excedent' => $excedent,
                    'statut'           => 'en_attente',
                    'note'             => "Trop-perçu sur paiement {$paiement->numero}",
                ]);
            }

            $this->creerRecu($paiementId, (int)$paiement->facture_id, (int)$facture->eleve_id, $montantApplique, $userId);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        $this->audit->logUpdate($userId, 'finance', 'paiement', $paiementId,
            ['statut' => $paiement->statut], ['statut' => 'complete']
        );

        EventDispatcher::dispatch(new PaymentCompleted(
            paiementId:           $paiementId,
            numero:               $paiement->numero,
            factureId:            (int)$paiement->facture_id,
            eleveId:              (int)$facture->eleve_id,
            montant:              (float)$paiement->montant,
            montantApplique:      $montantApplique,
            modePaiement:         $paiement->mode_code ?? '',
            nouveauStatutFacture: $nouveauStatut,
            completedById:        $userId,
        ));
    }

    // ----------------------------------------------------------------
    // Annulation — pas de DELETE (business rule)
    // ----------------------------------------------------------------
    public function annuler(int $paiementId, string $motif, int $userId): void
    {
        $paiement = $this->requirePaiement($paiementId);
        if (!in_array($paiement->statut, ['initie', 'valide'], true)) {
            throw new \RuntimeException(
                "Seuls les paiements initiés ou validés peuvent être annulés. " .
                "Un paiement complété doit être remboursé."
            );
        }
        if (empty($motif)) {
            throw new \InvalidArgumentException("Un motif d'annulation est requis.");
        }

        $this->repo->update($paiementId, [
            'statut'          => 'annule',
            'annule_par'      => $userId,
            'date_annulation' => date('Y-m-d'),
            'motif_annulation' => $motif,
        ]);

        $this->audit->logUpdate($userId, 'finance', 'paiement', $paiementId,
            ['statut' => $paiement->statut],
            ['statut' => 'annule', 'motif' => $motif]
        );

        EventDispatcher::dispatch(new PaymentCancelled(
            paiementId:    $paiementId,
            numero:        $paiement->numero,
            factureId:     (int)$paiement->facture_id,
            montantAnnule: (float)$paiement->montant,
            ancienStatut:  $paiement->statut,
            motif:         $motif,
            cancelledById: $userId,
        ));
    }

    // ----------------------------------------------------------------
    // Remboursement — paiement 'complete' → 'rembourse'
    // ----------------------------------------------------------------
    public function rembourser(int $paiementId, RefundDTO $dto, int $userId): int
    {
        $paiement = $this->requirePaiement($paiementId);
        if ($paiement->statut !== 'complete') {
            throw new \RuntimeException("Seuls les paiements complétés peuvent être remboursés.");
        }

        $errors = $dto->validate();
        if ($errors) {
            throw new \InvalidArgumentException(implode(' | ', $errors));
        }
        if ($dto->montant > (float)$paiement->montant_applique) {
            throw new \InvalidArgumentException(
                "Le montant du remboursement ne peut pas dépasser le montant du paiement."
            );
        }

        $facture = $this->requireFacture((int)$paiement->facture_id);

        $pdo = $this->repo->getPdo();
        $pdo->beginTransaction();
        try {
            $remboursementId = $this->repo->insertRemboursement([
                'paiement_id'             => $paiementId,
                'eleve_id'                => (int)$facture->eleve_id,
                'montant'                 => $dto->montant,
                'mode_remboursement'      => $dto->modeRemboursement,
                'reference_remboursement' => $dto->referenceRemboursement,
                'motif'                   => $dto->motif,
                'statut'                  => 'complete',
                'realise_par'             => $userId,
                'date_realisation'        => date('Y-m-d'),
            ]);

            $this->repo->update($paiementId, ['statut' => 'rembourse']);

            // Recalculer facture.montant_paye (ne plus compter ce paiement)
            $totalPaye = $this->repo->sumPaiementsComplete((int)$paiement->facture_id);
            $nouveauStatutFacture = $this->calculerStatutFactureDepuisMontant(
                $facture,
                $totalPaye
            );
            $this->repo->updateFacture((int)$paiement->facture_id, [
                'montant_paye' => $totalPaye,
                'statut'       => $nouveauStatutFacture,
            ]);

            // Si avoir avait été utilisé → le libérer
            if ($paiement->avoir_id) {
                $this->repo->updateAvoir((int)$paiement->avoir_id, [
                    'statut'     => 'emis',
                    'utilise_sur' => null,
                ]);
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        $this->audit->logUpdate($userId, 'finance', 'paiement', $paiementId,
            ['statut' => 'complete'],
            ['statut' => 'rembourse', 'motif' => $dto->motif]
        );

        EventDispatcher::dispatch(new PaymentRefunded(
            paiementId:       $paiementId,
            numero:           $paiement->numero,
            factureId:        (int)$paiement->facture_id,
            montantRembourse: $dto->montant,
            motif:            $dto->motif,
            refundedById:     $userId,
        ));

        return $remboursementId;
    }

    // ----------------------------------------------------------------
    // Reçu — récupère ou génère
    // ----------------------------------------------------------------
    public function getOuGenererRecu(int $paiementId, int $userId): object
    {
        $recu = $this->repo->findRecu($paiementId);
        if ($recu) {
            return $recu;
        }
        $paiement = $this->requirePaiement($paiementId);
        if ($paiement->statut !== 'complete') {
            throw new \RuntimeException("Un reçu ne peut être généré que pour un paiement complété.");
        }
        $facture  = $this->requireFacture((int)$paiement->facture_id);
        $this->creerRecu($paiementId, (int)$paiement->facture_id, (int)$facture->eleve_id, (float)$paiement->montant_applique, $userId);
        return $this->repo->findRecu($paiementId);
    }

    // ----------------------------------------------------------------
    // Trop-perçu — traitement
    // ----------------------------------------------------------------
    public function traiterTropPercu(int $tropPercuId, string $action, int $userId): void
    {
        $stmt = $this->repo->getPdo()->prepare(
            'SELECT * FROM `finance_trop_percus` WHERE `id` = ?'
        );
        $stmt->execute([$tropPercuId]);
        $tp = $stmt->fetch(\PDO::FETCH_OBJ);
        if (!$tp || $tp->statut !== 'en_attente') {
            throw new \RuntimeException("Trop-perçu introuvable ou déjà traité.");
        }

        $statut = match ($action) {
            'restituer' => 'restitue',
            'imputer'   => 'impute',
            'annuler'   => 'annule',
            default     => throw new \InvalidArgumentException("Action invalide : {$action}"),
        };

        $this->repo->updateTropPercu($tropPercuId, [
            'statut'          => $statut,
            'traite_par'      => $userId,
            'date_traitement' => date('Y-m-d'),
        ]);

        $this->audit->logUpdate($userId, 'finance', 'trop_percu', $tropPercuId,
            ['statut' => 'en_attente'], ['statut' => $statut]
        );
    }

    // ----------------------------------------------------------------
    // Internals
    // ----------------------------------------------------------------
    private function creerRecu(int $paiementId, int $factureId, int $eleveId, float $montant, int $userId): int
    {
        // Ne pas créer de doublon
        $existant = $this->repo->findRecu($paiementId);
        if ($existant) {
            return (int)$existant->id;
        }

        $numero = $this->repo->genererNumero('REC', (int)date('Y'));
        $recuId = $this->repo->insertRecu([
            'numero'        => $numero,
            'paiement_id'   => $paiementId,
            'facture_id'    => $factureId,
            'eleve_id'      => $eleveId,
            'montant'       => $montant,
            'date_emission' => date('Y-m-d'),
            'emis_par'      => $userId,
        ]);

        EventDispatcher::dispatch(new ReceiptGenerated(
            recuId:        $recuId,
            numero:        $numero,
            paiementId:    $paiementId,
            factureId:     $factureId,
            eleveId:       $eleveId,
            montant:       $montant,
            generatedById: $userId,
        ));

        return $recuId;
    }

    private function mettreAJourEcheance(int $echeanceId, float $montantApplique): void
    {
        $echeance = $this->repo->getEcheance($echeanceId);
        if (!$echeance) {
            return;
        }
        $nouveauPaye = round((float)$echeance->montant_paye + $montantApplique, 2);
        $statut = $nouveauPaye >= (float)$echeance->montant_du ? 'paye' : 'partiel';
        $this->repo->updateEcheance($echeanceId, [
            'montant_paye' => $nouveauPaye,
            'statut'       => $statut,
        ]);
    }

    private function calculerStatutFacture(object $facture, float $montantApplique): string
    {
        $totalPaye = round((float)$facture->montant_paye + $montantApplique, 2);
        return $this->calculerStatutFactureDepuisMontant($facture, $totalPaye);
    }

    private function calculerStatutFactureDepuisMontant(object $facture, float $totalPaye): string
    {
        $total = (float)$facture->montant_total;
        if ($totalPaye <= 0) {
            return in_array($facture->statut, ['payee','partiellement_payee'], true) ? 'emise' : $facture->statut;
        }
        if ($totalPaye >= $total) {
            return 'payee';
        }
        return 'partiellement_payee';
    }

    private function resolverMode(string $code): ?int
    {
        $mode = $this->repo->findModeByCode($code);
        return $mode ? (int)$mode->id : null;
    }

    private function validerPayable(object $facture): void
    {
        $statutsPayables = ['emise', 'partiellement_payee', 'en_retard'];
        if (!in_array($facture->statut, $statutsPayables, true)) {
            throw new \RuntimeException(
                "Cette facture n'est pas payable dans son état actuel ({$facture->statut}). " .
                "Seules les factures émises, partiellement payées ou en retard acceptent un paiement."
            );
        }
    }

    private function validerAvoir(int $avoirId, float $montant): void
    {
        $avoir = $this->repo->getAvoir($avoirId);
        if (!$avoir) {
            throw new \RuntimeException("Avoir #{$avoirId} introuvable.");
        }
        if ($avoir->statut !== 'emis') {
            throw new \RuntimeException("Cet avoir n'est pas disponible (statut : {$avoir->statut}).");
        }
        if ((float)$avoir->montant < $montant) {
            throw new \RuntimeException(
                "L'avoir est insuffisant (" . number_format((float)$avoir->montant, 2) .
                " XOF disponibles, " . number_format($montant, 2) . " XOF nécessaires)."
            );
        }
    }

    private function requirePaiement(int $id): object
    {
        $p = $this->repo->findWithDetails($id);
        if (!$p) {
            throw new \RuntimeException("Paiement #{$id} introuvable.");
        }
        return $p;
    }

    private function requireFacture(int $id): object
    {
        $f = $this->repo->getFacture($id);
        if (!$f) {
            throw new \RuntimeException("Facture #{$id} introuvable.");
        }
        return $f;
    }
}

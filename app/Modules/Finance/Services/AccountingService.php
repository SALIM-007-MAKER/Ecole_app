<?php

namespace App\Modules\Finance\Services;

use App\Modules\Finance\Contracts\AccountingInterface;
use App\Modules\Finance\DTO\ExerciceDTO;
use App\Modules\Finance\Events\PaymentCompleted;
use App\Modules\Finance\Events\PaymentRefunded;
use App\Modules\Finance\Events\CashMovementCreated;
use App\Modules\Finance\Events\ExpenseValidated;
use App\Modules\Finance\Events\InvoiceCancelled;
use App\Modules\Finance\Events\JournalEntryCreated;
use App\Modules\Finance\Events\FiscalYearClosed;
use App\Modules\Finance\Events\FiscalYearOpened;
use App\Modules\Finance\Repositories\AccountingRepository;
use App\Services\AuditService;
use Core\EventDispatcher;

class AccountingService implements AccountingInterface
{
    private AccountingRepository $repo;
    private AuditService         $audit;

    public function __construct()
    {
        $this->repo  = new AccountingRepository();
        $this->audit = new AuditService();
    }

    // ── Entrées automatiques depuis les événements ────────────────────────────

    public function enregistrerDepuisPaiement(PaymentCompleted $e, ?string $dateEcriture = null): void
    {
        $regle = $this->resoudreReglePaiement($e->modePaiement);

        // Vérifier qu'on n'a pas déjà créé une écriture pour ce paiement
        if ($this->repo->findEcritureByReference($e->numero, 'payment_completed')) {
            return;
        }

        $montant = $e->montantApplique;
        if ($montant <= 0) {
            return;
        }

        $this->creerEcriture(
            source:      'payment_completed',
            reference:   $e->numero,
            libelle:     "Encaissement {$e->numero}",
            journalCode: $regle->journal_code,
            lignes:      [
                ['compte' => $regle->compte_debit_code,  'libelle' => "Encaissement {$e->numero}", 'debit' => $montant, 'credit' => 0.0],
                ['compte' => $regle->compte_credit_code, 'libelle' => "Frais scolaires {$e->numero}", 'debit' => 0.0,     'credit' => $montant],
            ],
            userId:      $e->completedById,
            dateStr:     $dateEcriture,
        );
    }

    public function enregistrerDepuisRemboursement(PaymentRefunded $e): void
    {
        if ($this->repo->findEcritureByReference($e->numero, 'payment_refunded')) {
            return;
        }

        $mode = $this->repo->findPaiementMode($e->paiementId) ?? 'ESP';
        $typeSource = $this->repo->findModeCategorie($mode) === 'caisse' ? 'refund_esp' : 'refund_bnq';
        $regle = $this->repo->findRegle($typeSource);
        if (!$regle) {
            throw new \DomainException("Règle comptable '{$typeSource}' introuvable.");
        }

        $montant = $e->montantRembourse;
        if ($montant <= 0) {
            return;
        }

        $this->creerEcriture(
            source:      'payment_refunded',
            reference:   $e->numero,
            libelle:     "Remboursement {$e->numero}",
            journalCode: $regle->journal_code,
            lignes:      [
                ['compte' => $regle->compte_debit_code,  'libelle' => "Remboursement {$e->numero}", 'debit' => $montant, 'credit' => 0.0],
                ['compte' => $regle->compte_credit_code, 'libelle' => "Remboursement {$e->numero}", 'debit' => 0.0,     'credit' => $montant],
            ],
            userId:      $e->refundedById,
        );
    }

    public function enregistrerDepuisMouvementCaisse(CashMovementCreated $e): void
    {
        // Les mouvements issus de paiements et les mouvements système (ouverture/fermeture)
        // ne génèrent pas d'écritures comptables distinctes — ils sont déjà couverts
        // par enregistrerDepuisPaiement() ou sont des opérations internes de caisse.
        if (in_array($e->source, ['paiement', 'systeme'], true)) {
            return;
        }

        $typeSource = match ($e->type) {
            'recette'      => 'cash_recette_manuel',
            'decaissement' => 'cash_decaissement_manuel',
            default        => null,
        };

        if ($typeSource === null) {
            return; // correction et autres types non mapés
        }

        $regle = $this->repo->findRegle($typeSource);
        if (!$regle) {
            return;
        }

        $ref = 'MVT-' . $e->mouvementId;
        if ($this->repo->findEcritureByReference($ref, $typeSource)) {
            return;
        }

        $this->creerEcriture(
            source:      $typeSource,
            reference:   $ref,
            libelle:     $e->libelle,
            journalCode: $regle->journal_code,
            lignes:      [
                ['compte' => $regle->compte_debit_code,  'libelle' => $e->libelle, 'debit' => $e->montant, 'credit' => 0.0],
                ['compte' => $regle->compte_credit_code, 'libelle' => $e->libelle, 'debit' => 0.0,          'credit' => $e->montant],
            ],
            userId: $e->createdById,
        );
    }

    public function enregistrerDepuisDepense(ExpenseValidated $e): void
    {
        if ($this->repo->findEcritureByReference($e->numero, 'expense_validated')) {
            return;
        }

        $regle = $this->repo->findRegle('expense_validated');
        if (!$regle) {
            return;
        }

        $this->creerEcriture(
            source:      'expense_validated',
            reference:   $e->numero,
            libelle:     "Dépense {$e->numero} — {$e->libelle}",
            journalCode: $regle->journal_code,
            lignes:      [
                ['compte' => $regle->compte_debit_code,  'libelle' => $e->libelle, 'debit' => $e->montant, 'credit' => 0.0],
                ['compte' => $regle->compte_credit_code, 'libelle' => $e->libelle, 'debit' => 0.0,          'credit' => $e->montant],
            ],
            userId: $e->validatedById,
        );
    }

    public function enregistrerDepuisAnnulationFacture(InvoiceCancelled $e): void
    {
        // N'enregistrer une écriture que si un avoir a été créé (montant_paye > 0)
        if ($e->montantPaye <= 0 || $e->avoirId === null) {
            return;
        }

        if ($this->repo->findEcritureByReference($e->numero, 'invoice_cancelled')) {
            return;
        }

        $regle = $this->repo->findRegle('invoice_cancelled');
        if (!$regle) {
            return;
        }

        $this->creerEcriture(
            source:      'invoice_cancelled',
            reference:   $e->numero,
            libelle:     "Annulation facture {$e->numero}",
            journalCode: $regle->journal_code,
            lignes:      [
                ['compte' => $regle->compte_debit_code,  'libelle' => "Annulation {$e->numero}", 'debit' => $e->montantPaye, 'credit' => 0.0],
                ['compte' => $regle->compte_credit_code, 'libelle' => "Avoir élève #{$e->eleveId}", 'debit' => 0.0,        'credit' => $e->montantPaye],
            ],
            userId: $e->cancelledById,
        );
    }

    // ── Extourne ──────────────────────────────────────────────────────────────

    public function extourner(int $ecritureId, string $motif, int $userId): int
    {
        $ecriture = $this->repo->findEcriture($ecritureId);
        if (!$ecriture) {
            throw new \DomainException("Écriture #{$ecritureId} introuvable.");
        }
        if ($ecriture->statut !== 'valide') {
            throw new \DomainException("Seules les écritures validées peuvent être extournées.");
        }

        $lignesOriginales = $this->repo->getLignesByEcriture($ecritureId);
        if (empty($lignesOriginales)) {
            throw new \DomainException("L'écriture #{$ecritureId} n'a aucune ligne.");
        }

        // Inversion débit/crédit
        $lignesExtourne = array_map(fn($l) => [
            'compte'  => $l->compte_code,
            'libelle' => "Extourne — " . $l->libelle,
            'debit'   => (float)$l->credit,
            'credit'  => (float)$l->debit,
        ], $lignesOriginales);

        $newId = $this->creerEcriture(
            source:      'extourne',
            reference:   $ecriture->numero,
            libelle:     "Extourne {$ecriture->numero} — {$motif}",
            journalCode: $ecriture->journal_code,
            lignes:      $lignesExtourne,
            userId:      $userId,
            dateStr:     date('Y-m-d'),
        );

        // Marquer l'originale comme extournée
        $this->repo->updateEcriture($ecritureId, ['statut' => 'extourne']);

        $this->audit->log(
            $userId, 'extourne', 'Finance', 'ecriture', $ecritureId,
            ['statut' => 'valide'],
            ['statut' => 'extourne', 'extourne_vers' => $newId, 'motif' => $motif]
        );

        return $newId;
    }

    // ── Gestion des exercices et périodes ─────────────────────────────────────

    public function creerExercice(ExerciceDTO $dto, int $userId): int
    {
        $errors = $dto->validate();
        if (!empty($errors)) {
            throw new \InvalidArgumentException(implode(' | ', $errors));
        }

        $exerciceId = $this->repo->insertExercice([
            'libelle'      => $dto->libelle,
            'date_debut'   => $dto->dateDebut,
            'date_fin'     => $dto->dateFin,
            'solde_report' => $dto->soldeReport,
            'note'         => $dto->note,
            'ouvert_par'   => $userId,
        ]);

        $this->genererPeriodes($exerciceId, $dto->dateDebut, $dto->dateFin);

        EventDispatcher::dispatch(new FiscalYearOpened(
            exerciceId: $exerciceId,
            libelle:    $dto->libelle,
            dateDebut:  $dto->dateDebut,
            dateFin:    $dto->dateFin,
            openedById: $userId,
        ));

        $this->audit->log($userId, 'create', 'Finance', 'exercice', $exerciceId, null, $dto->toArray());

        return $exerciceId;
    }

    public function cloturerPeriode(int $periodeId, int $userId): void
    {
        $periode = $this->repo->findPeriode($periodeId);
        if (!$periode) {
            throw new \DomainException("Période #{$periodeId} introuvable.");
        }
        if ($periode->statut !== 'ouverte') {
            throw new \DomainException("La période est déjà clôturée.");
        }

        $this->repo->updatePeriode($periodeId, [
            'statut'      => 'cloturee',
            'cloture_par' => $userId,
            'cloture_le'  => date('Y-m-d H:i:s'),
        ]);

        $this->audit->log(
            $userId, 'cloturer_periode', 'Finance', 'periode_comptable', $periodeId,
            ['statut' => 'ouverte'],
            ['statut' => 'cloturee']
        );
    }

    public function cloturerExercice(int $exerciceId, int $userId): void
    {
        $exercice = $this->repo->findExercice($exerciceId);
        if (!$exercice) {
            throw new \DomainException("Exercice #{$exerciceId} introuvable.");
        }
        if (!in_array($exercice->statut, ['ouvert', 'reouvert'], true)) {
            throw new \DomainException("L'exercice est déjà clôturé.");
        }
        if ((int)($exercice->nb_periodes_ouvertes ?? 1) > 0) {
            throw new \DomainException(
                "Toutes les périodes doivent être clôturées avant de clôturer l'exercice. "
                . "{$exercice->nb_periodes_ouvertes} période(s) encore ouverte(s)."
            );
        }

        // Calcul du résultat de l'exercice
        $comptesProduits = $this->repo->getComptesByClasse(7, $exerciceId);
        $comptesCharges  = $this->repo->getComptesByClasse(6, $exerciceId);

        $totalProduits = array_sum(array_map(
            fn($c) => max(0, (float)$c->solde_credit - (float)$c->solde_debit), $comptesProduits
        ));
        $totalCharges  = array_sum(array_map(
            fn($c) => max(0, (float)$c->solde_debit - (float)$c->solde_credit), $comptesCharges
        ));
        $resultat = round($totalProduits - $totalCharges, 2);

        // Écriture de clôture (OD journal)
        $lignes = [];

        // Solde les comptes de produits (classe 7)
        foreach ($comptesProduits as $c) {
            $solde = round((float)$c->solde_credit - (float)$c->solde_debit, 2);
            if ($solde > 0) {
                $lignes[] = ['compte' => $c->code, 'libelle' => "Clôture — {$c->libelle}", 'debit' => $solde, 'credit' => 0.0];
            }
        }

        // Solde les comptes de charges (classe 6)
        foreach ($comptesCharges as $c) {
            $solde = round((float)$c->solde_debit - (float)$c->solde_credit, 2);
            if ($solde > 0) {
                $lignes[] = ['compte' => $c->code, 'libelle' => "Clôture — {$c->libelle}", 'debit' => 0.0, 'credit' => $solde];
            }
        }

        // Résultat vers compte 1200
        if ($resultat > 0) {
            $lignes[] = ['compte' => '1200', 'libelle' => "Résultat bénéficiaire — {$exercice->libelle}", 'debit' => 0.0, 'credit' => $resultat];
        } elseif ($resultat < 0) {
            $lignes[] = ['compte' => '1200', 'libelle' => "Résultat déficitaire — {$exercice->libelle}", 'debit' => abs($resultat), 'credit' => 0.0];
        }

        if (!empty($lignes)) {
            // Rouvrir temporairement une période pour l'écriture de clôture (dernière période)
            $periodes = $this->repo->getPeriodesByExercice($exerciceId);
            $dernierePeriode = end($periodes);
            if ($dernierePeriode) {
                $this->repo->updatePeriode($dernierePeriode->id, ['statut' => 'ouverte']);
            }

            $this->creerEcriture(
                source:      'exercice_cloture',
                reference:   $exercice->libelle,
                libelle:     "Clôture exercice — {$exercice->libelle}",
                journalCode: 'OD',
                lignes:      $lignes,
                userId:      $userId,
                dateStr:     $exercice->date_fin,
            );

            if ($dernierePeriode) {
                $this->repo->updatePeriode($dernierePeriode->id, [
                    'statut'      => 'cloturee',
                    'cloture_par' => $userId,
                    'cloture_le'  => date('Y-m-d H:i:s'),
                ]);
            }
        }

        $this->repo->updateExercice($exerciceId, [
            'statut'       => 'cloture',
            'date_cloture' => date('Y-m-d'),
            'cloture_par'  => $userId,
        ]);

        EventDispatcher::dispatch(new FiscalYearClosed(
            exerciceId:  $exerciceId,
            libelle:     $exercice->libelle,
            dateFin:     $exercice->date_fin,
            resultatNet: $resultat,
            closedById:  $userId,
        ));

        $this->audit->log(
            $userId, 'cloturer_exercice', 'Finance', 'exercice', $exerciceId,
            ['statut' => $exercice->statut],
            ['statut' => 'cloture', 'resultat_net' => $resultat]
        );
    }

    // ── Méthodes privées ──────────────────────────────────────────────────────

    private function creerEcriture(
        string  $source,
        string  $reference,
        string  $libelle,
        string  $journalCode,
        array   $lignes,
        int     $userId,
        ?string $dateStr = null,
    ): int {
        $date = $dateStr ?? date('Y-m-d');

        // Validation équilibre débit = crédit
        $totalDebit  = round(array_sum(array_column($lignes, 'debit')),  2);
        $totalCredit = round(array_sum(array_column($lignes, 'credit')), 2);
        if (abs($totalDebit - $totalCredit) > 0.01) {
            throw new \DomainException(
                "Écriture déséquilibrée — D={$totalDebit} C={$totalCredit} ({$source}/{$reference})"
            );
        }

        // Exercice courant
        $exercice = $this->repo->findExerciceCourant();
        if (!$exercice) {
            throw new \DomainException("Aucun exercice comptable ouvert. Créez un exercice via /v2/finance/comptabilite/exercices.");
        }

        // Période correspondant à la date
        $periode = $this->repo->findPeriodePourDate($exercice->id, $date);
        if (!$periode) {
            throw new \DomainException("Aucune période comptable pour la date {$date} dans l'exercice {$exercice->libelle}.");
        }
        if ($periode->statut === 'cloturee') {
            throw new \DomainException("La période {$periode->libelle} est clôturée — impossible d'y enregistrer des écritures.");
        }

        // Journal
        $journal = $this->repo->findJournalByCode($journalCode);
        if (!$journal) {
            throw new \DomainException("Journal comptable '{$journalCode}' introuvable.");
        }

        $numero = $this->repo->genererNumero((int)date('Y'));

        $pdo = $this->repo->getPdo();
        $pdo->beginTransaction();
        try {
            $ecritureId = $this->repo->insertEcriture([
                'numero'        => $numero,
                'date_ecriture' => $date,
                'journal_id'    => $journal->id,
                'exercice_id'   => $exercice->id,
                'periode_id'    => $periode->id,
                'libelle'       => $libelle,
                'reference'     => $reference,
                'source'        => $source,
                'statut'        => 'valide',
                'created_by'    => $userId,
            ]);

            foreach ($lignes as $ligne) {
                $compte = $this->repo->findCompteByCode($ligne['compte']);
                if (!$compte) {
                    throw new \DomainException("Compte comptable '{$ligne['compte']}' introuvable dans le plan comptable.");
                }
                $this->repo->insertLigneEcriture([
                    'ecriture_id' => $ecritureId,
                    'compte_id'   => $compte->id,
                    'libelle'     => $ligne['libelle'] ?? $libelle,
                    'debit'       => (float)$ligne['debit'],
                    'credit'      => (float)$ligne['credit'],
                    'created_by'  => $userId,
                ]);
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        EventDispatcher::dispatch(new JournalEntryCreated(
            ecritureId:  $ecritureId,
            numero:      $numero,
            source:      $source,
            reference:   $reference,
            totalDebit:  $totalDebit,
            journalCode: $journalCode,
            createdById: $userId,
        ));

        return $ecritureId;
    }

    private function resoudreReglePaiement(string $modePaiement): object
    {
        $typeSource = match ($this->repo->findModeCategorie($modePaiement)) {
            'caisse' => 'payment_esp',
            'banque' => 'payment_bnq',
            default  => 'payment_avoir',
        };

        $regle = $this->repo->findRegle($typeSource);
        if (!$regle) {
            throw new \DomainException("Règle comptable '{$typeSource}' introuvable.");
        }
        return $regle;
    }

    private function genererPeriodes(int $exerciceId, string $dateDebut, string $dateFin): void
    {
        $current = new \DateTime($dateDebut);
        $fin     = new \DateTime($dateFin);
        $ordre   = 1;

        while ($current <= $fin && $ordre <= 12) {
            $periodeFin = clone $current;
            $periodeFin->modify('last day of this month');
            if ($periodeFin > $fin) {
                $periodeFin = clone $fin;
            }

            $this->repo->insertPeriode([
                'exercice_id' => $exerciceId,
                'numero'      => $ordre,
                'libelle'     => $current->format('F Y'),
                'date_debut'  => $current->format('Y-m-d'),
                'date_fin'    => $periodeFin->format('Y-m-d'),
            ]);

            $current->modify('first day of next month');
            $ordre++;
        }
    }
}

<?php

namespace App\Modules\Finance\Services;

use App\Modules\Finance\Contracts\CaisseInterface;
use App\Modules\Finance\DTO\CashRegisterDTO;
use App\Modules\Finance\DTO\CashMovementDTO;
use App\Modules\Finance\Events\CashRegisterOpened;
use App\Modules\Finance\Events\CashRegisterClosed;
use App\Modules\Finance\Events\CashMovementCreated;
use App\Modules\Finance\Events\CashMovementCancelled;
use App\Modules\Finance\Events\CashBalanceUpdated;
use App\Modules\Finance\Repositories\CashRegisterRepository;
use App\Modules\Finance\Repositories\CashMovementRepository;
use App\Services\AuditService;
use Core\EventDispatcher;

class CashRegisterService implements CaisseInterface
{
    private CashRegisterRepository $repo;
    private CashMovementRepository $mouvRepo;
    private AuditService           $audit;

    public function __construct()
    {
        $this->repo     = new CashRegisterRepository();
        $this->mouvRepo = new CashMovementRepository();
        $this->audit    = new AuditService();
    }

    // ----------------------------------------------------------------
    // Ouverture de caisse
    // Règle : une seule caisse ouverte par caissier
    // ----------------------------------------------------------------
    public function ouvrir(CashRegisterDTO $dto, int $userId): int
    {
        $errors = $dto->validate();
        if ($errors) {
            throw new \InvalidArgumentException(implode(' | ', $errors));
        }

        $sessionActive = $this->repo->findSessionActive($userId);
        if ($sessionActive) {
            throw new \RuntimeException(
                "Une session de caisse est déjà ouverte pour cet utilisateur (n° {$sessionActive->numero})."
            );
        }

        $pdo = $this->repo->getPdo();
        $pdo->beginTransaction();
        try {
            $numero    = $this->repo->genererNumero('CSE', (int)date('Y'));
            $maintenant = new \DateTime();

            $sessionId = $this->repo->insert([
                'numero'          => $numero,
                'caissier_id'     => $userId,
                'statut'          => 'ouverte',
                'date_ouverture'  => $maintenant->format('Y-m-d'),
                'heure_ouverture' => $maintenant->format('H:i:s'),
                'solde_initial'   => $dto->soldeInitial,
                'total_recettes'  => 0,
                'total_decaissements' => 0,
                'solde_theorique' => $dto->soldeInitial,
                'note_ouverture'  => $dto->note,
                'ouvert_par'      => $userId,
            ]);

            // Mouvement d'ouverture (audit uniquement, non comptabilisé dans recettes)
            if ($dto->soldeInitial > 0) {
                $this->mouvRepo->insert([
                    'session_id' => $sessionId,
                    'type'       => 'ouverture',
                    'sens'       => 'credit',
                    'montant'    => $dto->soldeInitial,
                    'libelle'    => 'Fonds d\'ouverture de caisse',
                    'reference'  => $numero,
                    'source'     => 'systeme',
                    'statut'     => 'actif',
                    'created_by' => $userId,
                ]);
            }

            // Journal du jour
            $this->repo->upsertJournal([
                'date_journee'    => $maintenant->format('Y-m-d'),
                'session_id'      => $sessionId,
                'solde_ouverture' => $dto->soldeInitial,
                'total_entrees'   => 0,
                'total_sorties'   => 0,
                'solde_cloture'   => $dto->soldeInitial,
                'statut'          => 'ouvert',
            ]);

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        EventDispatcher::dispatch(new CashRegisterOpened(
            sessionId:    $sessionId,
            numero:       $numero,
            caissierId:   $userId,
            soldeInitial: $dto->soldeInitial,
            openedById:   $userId,
        ));

        return $sessionId;
    }

    // ----------------------------------------------------------------
    // Fermeture de caisse
    // Calcule automatiquement : total recettes, décaissements,
    // solde théorique, écart
    // ----------------------------------------------------------------
    public function fermer(int $sessionId, float $soldeReel, ?string $note, int $userId): void
    {
        $session = $this->requireSession($sessionId);

        if (!in_array($session->statut, ['ouverte', 'en_activite'], true)) {
            throw new \RuntimeException('Cette session de caisse ne peut pas être fermée (statut : ' . $session->statut . ').');
        }

        $totaux = $this->repo->calculerTotaux($sessionId);
        $soldeTheorique = round(
            (float)$session->solde_initial + (float)$totaux->total_credits - (float)$totaux->total_debits,
            2
        );
        $ecart       = round($soldeReel - $soldeTheorique, 2);
        $maintenant  = new \DateTime();

        $pdo = $this->repo->getPdo();
        $pdo->beginTransaction();
        try {
            $this->repo->update($sessionId, [
                'statut'               => 'fermee',
                'date_fermeture'       => $maintenant->format('Y-m-d'),
                'heure_fermeture'      => $maintenant->format('H:i:s'),
                'total_recettes'       => (float)$totaux->total_recettes,
                'total_decaissements'  => (float)$totaux->total_decaissements,
                'solde_theorique'      => $soldeTheorique,
                'solde_reel'           => $soldeReel,
                'ecart'                => $ecart,
                'note_fermeture'       => $note,
                'ferme_par'            => $userId,
            ]);

            // Mouvement de fermeture (audit, montant = solde réel)
            $this->mouvRepo->insert([
                'session_id' => $sessionId,
                'type'       => 'fermeture',
                'sens'       => 'credit',
                'montant'    => $soldeReel,
                'libelle'    => 'Fermeture de caisse — solde remis',
                'reference'  => $session->numero,
                'source'     => 'systeme',
                'statut'     => 'actif',
                'created_by' => $userId,
            ]);

            // Mise à jour du journal
            $this->repo->upsertJournal([
                'date_journee'    => $session->date_ouverture,
                'session_id'      => $sessionId,
                'solde_ouverture' => (float)$session->solde_initial,
                'total_entrees'   => (float)$totaux->total_credits,
                'total_sorties'   => (float)$totaux->total_debits,
                'solde_cloture'   => $soldeTheorique,
                'statut'          => 'clos',
            ]);

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        EventDispatcher::dispatch(new CashRegisterClosed(
            sessionId:           $sessionId,
            numero:              $session->numero,
            caissierId:          (int)$session->caissier_id,
            totalRecettes:       (float)$totaux->total_recettes,
            totalDecaissements:  (float)$totaux->total_decaissements,
            soldeTheorique:      $soldeTheorique,
            soldeReel:           $soldeReel,
            ecart:               $ecart,
            closedById:          $userId,
        ));
    }

    // ----------------------------------------------------------------
    // Enregistrement manuel d'un mouvement de caisse
    // Transition automatique ouverte → en_activite au premier mouvement
    // ----------------------------------------------------------------
    public function enregistrerMouvement(int $sessionId, CashMovementDTO $dto, int $userId): int
    {
        $errors = $dto->validate();
        if ($errors) {
            throw new \InvalidArgumentException(implode(' | ', $errors));
        }

        $session = $this->requireSession($sessionId);
        if (!in_array($session->statut, ['ouverte', 'en_activite'], true)) {
            throw new \RuntimeException('Impossible d\'enregistrer un mouvement : la session est ' . $session->statut . '.');
        }

        $ancienSolde = (float)$session->solde_theorique;

        $pdo = $this->repo->getPdo();
        $pdo->beginTransaction();
        try {
            $mouvementId = $this->mouvRepo->insert([
                'session_id'  => $sessionId,
                'type'        => $dto->type,
                'sens'        => $dto->sens,
                'montant'     => $dto->montant,
                'libelle'     => $dto->libelle,
                'reference'   => $dto->reference,
                'paiement_id' => $dto->paiementId,
                'source'      => $dto->source,
                'statut'      => 'actif',
                'created_by'  => $userId,
            ]);

            // Recalcul des totaux
            $totaux = $this->repo->calculerTotaux($sessionId);
            $nouveauSolde = round(
                (float)$session->solde_initial + (float)$totaux->total_credits - (float)$totaux->total_debits,
                2
            );

            $updates = [
                'total_recettes'      => (float)$totaux->total_recettes,
                'total_decaissements' => (float)$totaux->total_decaissements,
                'solde_theorique'     => $nouveauSolde,
            ];
            // Transition ouverte → en_activite au premier mouvement manuel
            if ($session->statut === 'ouverte') {
                $updates['statut'] = 'en_activite';
            }
            $this->repo->update($sessionId, $updates);

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        EventDispatcher::dispatch(new CashMovementCreated(
            mouvementId: $mouvementId,
            sessionId:   $sessionId,
            type:        $dto->type,
            sens:        $dto->sens,
            montant:     $dto->montant,
            libelle:     $dto->libelle,
            source:      $dto->source,
            createdById: $userId,
        ));

        EventDispatcher::dispatch(new CashBalanceUpdated(
            sessionId:    $sessionId,
            ancienSolde:  $ancienSolde,
            nouveauSolde: $nouveauSolde ?? $ancienSolde,
            updatedById:  $userId,
        ));

        return $mouvementId;
    }

    // ----------------------------------------------------------------
    // Annulation d'un mouvement
    // Règle : aucun DELETE — correction par opération inverse
    // ----------------------------------------------------------------
    public function annulerMouvement(int $mouvementId, string $motif, int $userId): void
    {
        $mouvement = $this->mouvRepo->find($mouvementId);
        if (!$mouvement) {
            throw new \RuntimeException('Mouvement introuvable.');
        }
        if ($mouvement->statut === 'annule') {
            throw new \RuntimeException('Ce mouvement est déjà annulé.');
        }
        if (!in_array($mouvement->type, ['recette', 'decaissement', 'correction'], true)) {
            throw new \RuntimeException('Les mouvements système ne peuvent pas être annulés manuellement.');
        }

        $session = $this->requireSession((int)$mouvement->session_id);
        if (!in_array($session->statut, ['ouverte', 'en_activite'], true)) {
            throw new \RuntimeException('Impossible d\'annuler un mouvement sur une session fermée.');
        }

        $ancienSolde = (float)$session->solde_theorique;
        $sensinverse = $mouvement->sens === 'credit' ? 'debit' : 'credit';

        $pdo = $this->repo->getPdo();
        $pdo->beginTransaction();
        try {
            // Marquer l'original comme annulé
            $this->mouvRepo->update($mouvementId, [
                'statut'           => 'annule',
                'annule_par'       => $userId,
                'motif_annulation' => $motif,
                'date_annulation'  => date('Y-m-d H:i:s'),
            ]);

            // Mouvement inverse (opération de correction)
            $this->mouvRepo->insert([
                'session_id' => $mouvement->session_id,
                'type'       => 'annulation',
                'sens'       => $sensinverse,
                'montant'    => (float)$mouvement->montant,
                'libelle'    => 'Annulation — ' . $mouvement->libelle,
                'reference'  => 'ANN-' . $mouvementId,
                'source'     => 'systeme',
                'statut'     => 'actif',
                'created_by' => $userId,
            ]);

            // Recalcul
            $totaux = $this->repo->calculerTotaux((int)$mouvement->session_id);
            $nouveauSolde = round(
                (float)$session->solde_initial + (float)$totaux->total_credits - (float)$totaux->total_debits,
                2
            );
            $this->repo->update((int)$mouvement->session_id, [
                'total_recettes'      => (float)$totaux->total_recettes,
                'total_decaissements' => (float)$totaux->total_decaissements,
                'solde_theorique'     => $nouveauSolde,
            ]);

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        EventDispatcher::dispatch(new CashMovementCancelled(
            mouvementId:   $mouvementId,
            sessionId:     (int)$mouvement->session_id,
            montant:       (float)$mouvement->montant,
            motif:         $motif,
            cancelledById: $userId,
        ));

        EventDispatcher::dispatch(new CashBalanceUpdated(
            sessionId:    (int)$mouvement->session_id,
            ancienSolde:  $ancienSolde,
            nouveauSolde: $nouveauSolde ?? $ancienSolde,
            updatedById:  $userId,
        ));
    }

    // ----------------------------------------------------------------
    // Alimentation automatique depuis un paiement validé (via Event)
    // Appelé par CashRegisterHandler sur PaymentCompleted
    // ----------------------------------------------------------------
    public function crediterDepuisPaiement(
        int    $paiementId,
        string $numeroPaiement,
        float  $montantApplique,
        string $modePaiement,
        int    $completedById
    ): void {
        // Ignorer les paiements par avoir (déjà un avoir en caisse)
        if ($modePaiement === 'AVOIR') {
            return;
        }

        $session = $this->repo->findSessionActive($completedById);
        if (!$session) {
            // Pas de session ouverte pour ce caissier — pas d'erreur, on passe
            return;
        }

        // Vérifier qu'on n'a pas déjà imputé ce paiement
        $existant = $this->mouvRepo->findByPaiement($paiementId);
        if ($existant) {
            return;
        }

        $dto = new CashMovementDTO(
            type:       'recette',
            sens:       'credit',
            montant:    $montantApplique,
            libelle:    'Encaissement paiement ' . $numeroPaiement,
            reference:  $numeroPaiement,
            source:     'paiement',
            paiementId: $paiementId,
        );

        $this->enregistrerMouvement((int)$session->id, $dto, $completedById);
    }

    // ----------------------------------------------------------------
    // Rapprochement de caisse (clôture journal)
    // ----------------------------------------------------------------
    public function rapprocher(int $sessionId, string $note, int $userId): void
    {
        $session = $this->requireSession($sessionId);
        if ($session->statut !== 'fermee') {
            throw new \RuntimeException('Le rapprochement ne peut s\'effectuer que sur une session fermée.');
        }

        $journal = $this->repo->getJournal($sessionId);
        if (!$journal) {
            throw new \RuntimeException('Journal de caisse introuvable pour cette session.');
        }
        if ($journal->statut === 'rapproche') {
            throw new \RuntimeException('Ce journal est déjà rapproché.');
        }

        $this->repo->upsertJournal([
            'date_journee'    => $session->date_ouverture,
            'session_id'      => $sessionId,
            'solde_ouverture' => (float)$session->solde_initial,
            'total_entrees'   => (float)$journal->total_entrees,
            'total_sorties'   => (float)$journal->total_sorties,
            'solde_cloture'   => (float)$journal->solde_cloture,
            'statut'          => 'rapproche',
            'note'            => $note,
        ]);

        $this->audit->log($userId, 'caisse.rapprocher', 'finance', 'session_caisse', $sessionId, null, ['statut' => 'rapproche']);
    }

    // ----------------------------------------------------------------
    // Getters
    // ----------------------------------------------------------------
    public function getSessionActive(int $userId): ?object
    {
        return $this->repo->findSessionActive($userId);
    }

    // ----------------------------------------------------------------
    // Privé
    // ----------------------------------------------------------------
    private function requireSession(int $sessionId): object
    {
        $session = $this->repo->findWithDetails($sessionId);
        if (!$session) {
            throw new \RuntimeException('Session de caisse introuvable.');
        }
        return $session;
    }
}

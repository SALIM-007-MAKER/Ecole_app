<?php

declare(strict_types=1);

namespace App\Modules\RH\Formations\Services;

use Core\EventDispatcher;
use App\Modules\RH\Formations\DTO\TrainingDTO;
use App\Modules\RH\Formations\DTO\SessionDTO;
use App\Modules\RH\Formations\Models\TrainingModel;
use App\Modules\RH\Formations\Repositories\TrainingRepository;
use App\Modules\RH\Formations\Events\TrainingCreated;
use App\Modules\RH\Formations\Events\TrainingSessionOpened;
use App\Modules\RH\Formations\Events\EmployeeEnrolled;
use App\Modules\RH\Formations\Events\TrainingCompleted;

class TrainingService
{
    private TrainingRepository $repo;

    public function __construct()
    {
        $this->repo = new TrainingRepository();
    }

    // ── Catalogue ─────────────────────────────────────────────────────────────

    public function creerFormation(TrainingDTO $dto, int $userId): int
    {
        $errors = $dto->validate();
        if ($errors) throw new \InvalidArgumentException(implode(' ', $errors));

        $id = $this->repo->insertFormation([
            'code'               => $dto->code,
            'titre'              => $dto->titre,
            'description'        => $dto->description,
            'type'               => $dto->type,
            'duree_heures'       => $dto->dureeHeures,
            'niveau'             => $dto->niveau,
            'modalite'           => $dto->modalite,
            'organisme_id'       => $dto->organismeId,
            'formateur_principal'=> $dto->formateurPrincipal,
            'cout_unitaire'      => $dto->coutUnitaire,
            'max_participants'   => $dto->maxParticipants,
            'prerequis'          => $dto->prerequis,
            'objectifs'          => $dto->objectifs,
            'created_by'         => $userId,
        ]);

        foreach ($dto->competenceIds as $cid) {
            if ($cid > 0) $this->repo->insertFormationCompetence($id, $cid);
        }

        EventDispatcher::dispatch(new TrainingCreated(
            $id, $dto->code, $dto->titre, $dto->type, $userId
        ));

        return $id;
    }

    // ── Sessions ──────────────────────────────────────────────────────────────

    public function creerSession(SessionDTO $dto, int $userId): int
    {
        $errors = $dto->validate();
        if ($errors) throw new \InvalidArgumentException(implode(' ', $errors));

        $formation = $this->repo->findFormationById($dto->formationId);
        if (!$formation) throw new \RuntimeException('Formation introuvable.');

        return $this->repo->insertSession([
            'formation_id'    => $dto->formationId,
            'code_session'    => $dto->codeSession,
            'lieu'            => $dto->lieu,
            'date_debut'      => $dto->dateDebut,
            'date_fin'        => $dto->dateFin,
            'heure_debut'     => $dto->heureDebut,
            'heure_fin'       => $dto->heureFin,
            'max_participants'=> $dto->maxParticipants,
            'formateur_nom'   => $dto->formateurNom,
            'cout_total'      => $dto->coutTotal,
            'financeur'       => $dto->financeur,
            'commentaire'     => $dto->commentaire,
            'created_by'      => $userId,
        ]);
    }

    public function ouvrirSession(int $id, int $userId): void
    {
        $session = $this->requireSession($id);
        $this->assertSessionTransition($session, 'ouvrir');

        $this->repo->updateSessionStatut($id, 'ouverte');

        EventDispatcher::dispatch(new TrainingSessionOpened(
            $id, (int)$session['formation_id'], $session['code_session'],
            $session['date_debut'], $session['date_fin'], $userId
        ));
    }

    public function demarrerSession(int $id, int $userId): void
    {
        $session = $this->requireSession($id);
        $this->assertSessionTransition($session, 'demarrer');
        $this->repo->updateSessionStatut($id, 'en_cours');
    }

    public function terminerSession(int $id, int $userId): void
    {
        $session = $this->requireSession($id);
        $this->assertSessionTransition($session, 'terminer');
        $this->repo->updateSessionStatut($id, 'terminee');
    }

    public function annulerSession(int $id, int $userId): void
    {
        $session = $this->requireSession($id);
        $this->assertSessionTransition($session, 'annuler');
        $this->repo->updateSessionStatut($id, 'annulee');
    }

    // ── Inscriptions ──────────────────────────────────────────────────────────

    public function inscrire(int $sessionId, int $employeId, int $userId): int
    {
        $session = $this->requireSession($sessionId);

        if (!in_array($session['statut'], ['ouverte', 'planifiee'], true)) {
            throw new \RuntimeException('La session n\'accepte plus d\'inscriptions (statut : ' . $session['statut'] . ').');
        }

        if ((int)$session['nb_inscrits'] >= (int)$session['max_participants']) {
            throw new \RuntimeException('Session complète (' . $session['max_participants'] . ' participants max).');
        }

        $existing = $this->repo->findInscription($sessionId, $employeId);
        if ($existing && $existing['statut'] !== 'annule') {
            throw new \RuntimeException('Cet employé est déjà inscrit à cette session.');
        }

        $inscId = $this->repo->insertInscription([
            'session_id'  => $sessionId,
            'employe_id'  => $employeId,
            'created_by'  => $userId,
        ]);

        $this->repo->incrementNbInscrits($sessionId, 1);

        EventDispatcher::dispatch(new EmployeeEnrolled(
            $inscId, $sessionId, $employeId,
            $session['formation_titre'] ?? $session['code_session'],
            $userId
        ));

        return $inscId;
    }

    public function confirmerInscription(int $inscriptionId, int $userId): void
    {
        $insc = $this->requireInscription($inscriptionId);
        if ($insc['statut'] !== 'inscrit') throw new \RuntimeException('L\'inscription ne peut pas être confirmée.');
        $this->repo->updateInscriptionStatut($inscriptionId, 'confirme', [
            'date_confirmation' => date('Y-m-d H:i:s'),
        ], $userId);
    }

    public function marquerPresence(int $inscriptionId, bool $present, int $userId): void
    {
        $insc = $this->requireInscription($inscriptionId);
        if (!in_array($insc['statut'], ['inscrit', 'confirme', 'present', 'absent'], true)) {
            throw new \RuntimeException('Statut incompatible pour pointage de présence.');
        }
        $this->repo->updateInscriptionStatut(
            $inscriptionId,
            $present ? 'present' : 'absent',
            [],
            $userId
        );
    }

    public function validerInscription(int $inscriptionId, ?float $note, ?string $commentaire, int $userId): void
    {
        $insc = $this->requireInscription($inscriptionId);
        if (!in_array($insc['statut'], ['present', 'confirme', 'inscrit'], true)) {
            throw new \RuntimeException('Impossible de valider depuis le statut : ' . $insc['statut'] . '.');
        }

        $this->repo->updateInscriptionStatut($inscriptionId, 'valide', [
            'date_validation'       => date('Y-m-d H:i:s'),
            'note_evaluation'       => $note,
            'commentaire_evaluation'=> $commentaire,
            'attestation_delivree'  => 1,
        ], $userId);

        EventDispatcher::dispatch(new TrainingCompleted(
            $inscriptionId, (int)$insc['session_id'], (int)$insc['employe_id'],
            'valide', $note, $userId
        ));
    }

    public function annulerInscription(int $inscriptionId, int $userId): void
    {
        $insc = $this->requireInscription($inscriptionId);
        if (in_array($insc['statut'], ['valide', 'annule'], true)) {
            throw new \RuntimeException('Impossible d\'annuler une inscription validée ou déjà annulée.');
        }
        $this->repo->updateInscriptionStatut($inscriptionId, 'annule', [], $userId);
        $this->repo->incrementNbInscrits((int)$insc['session_id'], -1);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function requireSession(int $id): array
    {
        $s = $this->repo->findSessionById($id);
        if (!$s) throw new \RuntimeException('Session introuvable.');
        return $s;
    }

    private function requireInscription(int $id): array
    {
        $i = $this->repo->findInscriptionById($id);
        if (!$i) throw new \RuntimeException('Inscription introuvable.');
        return $i;
    }

    private function assertSessionTransition(array $session, string $action): void
    {
        if (!TrainingModel::canSessionTransition($session['statut'], $action)) {
            throw new \RuntimeException(
                "Transition \"$action\" interdite depuis statut \"{$session['statut']}\"."
            );
        }
    }
}

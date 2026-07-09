<?php

namespace App\Modules\VieScolaire\Presences\Services;

use App\Modules\VieScolaire\Absences\Repositories\AbsenceRepository;
use App\Modules\VieScolaire\Presences\DTO\AttendanceFiltersDTO;
use App\Modules\VieScolaire\Presences\DTO\AttendanceSessionDTO;
use App\Modules\VieScolaire\Presences\DTO\PresenceDTO;
use App\Modules\VieScolaire\Presences\Events\AttendanceCompleted;
use App\Modules\VieScolaire\Presences\Events\AttendanceStarted;
use App\Modules\VieScolaire\Presences\Events\AttendanceValidated;
use App\Modules\VieScolaire\Presences\Events\StudentAbsent;
use App\Modules\VieScolaire\Presences\Events\StudentLate;
use App\Modules\VieScolaire\Presences\Events\StudentPresent;
use App\Modules\VieScolaire\Presences\Repositories\AttendanceRepository;
use App\Modules\VieScolaire\Retards\Events\StudentLate as RetardStudentLate;
use App\Modules\VieScolaire\Retards\Repositories\LateRepository;
use App\Services\AuditService;
use Core\EventDispatcher;

class AttendanceService
{
    private AttendanceRepository $repo;
    private AbsenceRepository    $absenceRepo;
    private LateRepository       $lateRepo;
    private AuditService         $audit;

    public function __construct()
    {
        $this->repo        = new AttendanceRepository();
        $this->absenceRepo = new AbsenceRepository();
        $this->lateRepo    = new LateRepository();
        $this->audit       = new AuditService();
    }

    // ── Lecture ───────────────────────────────────────────────────────────────

    public function findSession(int $id): ?array
    {
        return $this->repo->findSessionById($id);
    }

    public function paginateSessions(AttendanceFiltersDTO $filters): array
    {
        $rows  = $this->repo->findSessions($filters);
        $total = $this->repo->countSessions($filters);

        return [
            'data'      => $rows,
            'total'     => $total,
            'page'      => $filters->page,
            'per_page'  => $filters->perPage,
            'last_page' => (int)ceil($total / max($filters->perPage, 1)),
        ];
    }

    public function getSessionWithPresences(int $sessionId): array
    {
        $session   = $this->repo->findSessionById($sessionId);
        if ($session === null) {
            throw new \RuntimeException("Session d'appel introuvable.");
        }

        $presences = $this->repo->findPresencesBySession($sessionId);
        $eleves    = $this->repo->findElevesByClasse((int)$session['classe_id']);
        $stats     = $this->repo->statsParSession($sessionId);

        // Index presences by eleve_id for easy lookup in the view
        $presencesIndex = [];
        foreach ($presences as $p) {
            $presencesIndex[(int)$p['eleve_id']] = $p;
        }

        return compact('session', 'presences', 'eleves', 'stats', 'presencesIndex');
    }

    public function getSessionHistory(int $sessionId): array
    {
        return $this->repo->findHistoriqueBySession($sessionId);
    }

    public function statsClasse(int $classeId, string $anneeScolaire): array
    {
        return $this->repo->statsParClasse($classeId, $anneeScolaire);
    }

    // ── Ouverture d'une session ───────────────────────────────────────────────

    public function ouvrirSession(AttendanceSessionDTO $dto, int $enseignantId, string $anneeScolaire): int
    {
        $errors = $dto->validate();
        if (!empty($errors)) {
            throw new \InvalidArgumentException(
                implode(' | ', array_merge(...array_values($errors)))
            );
        }

        // Contrôle doublon : une seule session par classe/date/heure
        $existing = $this->repo->findSessionByKey($dto->classeId, $dto->dateAppel, $dto->heureDebut);
        if ($existing !== null) {
            throw new \RuntimeException(
                "Un appel existe déjà pour cette classe à cette date" .
                ($dto->heureDebut ? " à {$dto->heureDebut}" : '') . "."
            );
        }

        $data = array_merge($dto->toArray(), [
            'enseignant_id'  => $enseignantId,
            'annee_scolaire' => $anneeScolaire,
        ]);

        $sessionId = $this->repo->insertSession($data);

        $this->audit->logCreate($enseignantId, 'vie_scolaire', 'appel', $sessionId, $data);

        EventDispatcher::dispatch(new AttendanceStarted(
            appelId:       $sessionId,
            classeId:      $dto->classeId,
            matiereId:     $dto->matiereId,
            enseignantId:  $enseignantId,
            dateAppel:     $dto->dateAppel,
            typeAppel:     $dto->typeAppel,
            anneeScolaire: $anneeScolaire,
        ));

        return $sessionId;
    }

    // ── Pointage en masse ─────────────────────────────────────────────────────

    /**
     * Enregistre ou met à jour le pointage pour tous les élèves d'une session.
     *
     * @param int $sessionId
     * @param PresenceDTO[] $presenceDTOs  Indexed by eleve_id
     * @param int $userId                  Enseignant ou admin qui saisit
     */
    public function enregistrerPointages(int $sessionId, array $presenceDTOs, int $userId): void
    {
        $session = $this->repo->findSessionById($sessionId);
        if ($session === null) {
            throw new \RuntimeException("Session introuvable.");
        }
        if ($session['statut'] === 'valide') {
            throw new \RuntimeException("Cette session est validée et ne peut plus être modifiée.");
        }

        foreach ($presenceDTOs as $dto) {
            $this->pointEleve($sessionId, $session, $dto, $userId);
        }

        // Vérifier si la session est maintenant complète
        $eleves   = $this->repo->findElevesByClasse((int)$session['classe_id']);
        $presences = $this->repo->findPresencesBySession($sessionId);

        if (count($presences) === count($eleves) && count($eleves) > 0) {
            EventDispatcher::dispatch(new AttendanceCompleted(
                appelId:      $sessionId,
                classeId:     (int)$session['classe_id'],
                dateAppel:    $session['date_appel'],
                totalEleves:  count($eleves),
                pointesCount: count($presences),
            ));
        }

        $this->audit->log($userId, 'pointer', 'vie_scolaire', 'appel', $sessionId);
    }

    // ── Validation de session ─────────────────────────────────────────────────

    public function validerSession(int $sessionId, int $userId): void
    {
        $session = $this->repo->findSessionById($sessionId);
        if ($session === null) {
            throw new \RuntimeException("Session introuvable.");
        }
        if ($session['statut'] === 'valide') {
            throw new \RuntimeException("Cette session est déjà validée.");
        }

        $stats = $this->repo->statsParSession($sessionId);
        if (empty($stats) || (int)$stats['total'] === 0) {
            throw new \RuntimeException(
                "Impossible de valider un appel vide. Pointez au moins un élève."
            );
        }

        $ok = $this->repo->validateSession($sessionId, $userId);
        if (!$ok) {
            throw new \RuntimeException("La validation a échoué.");
        }

        $this->audit->log($userId, 'validate', 'vie_scolaire', 'appel', $sessionId);

        EventDispatcher::dispatch(new AttendanceValidated(
            appelId:       $sessionId,
            classeId:      (int)$session['classe_id'],
            enseignantId:  (int)$session['enseignant_id'],
            dateAppel:     $session['date_appel'],
            totalEleves:   (int)($stats['total']    ?? 0),
            totalPresents: (int)($stats['presents']  ?? 0),
            totalAbsents:  (int)($stats['absents']   ?? 0),
            valideParId:   $userId,
        ));
    }

    // ── Archivage (soft delete, brouillons seulement) ─────────────────────────

    public function archiverSession(int $sessionId, int $userId): void
    {
        $session = $this->repo->findSessionById($sessionId);
        if ($session === null) {
            throw new \RuntimeException("Session introuvable.");
        }
        if ($session['statut'] === 'valide') {
            throw new \RuntimeException("Un appel validé ne peut pas être supprimé.");
        }

        $this->repo->softDeleteSession($sessionId);
        $this->audit->logDelete($userId, 'vie_scolaire', 'appel', $sessionId, $session);
    }

    // ── Pointer un seul élève ─────────────────────────────────────────────────

    private function pointEleve(int $sessionId, array $session, PresenceDTO $dto, int $userId): void
    {
        $existing  = $this->repo->findPresenceByEleveAndSession($dto->eleveId, $sessionId);
        $absenceId = null;
        $retardId  = null;

        // Création automatique d'une absence dans vs_absences si statut = absent
        if ($dto->statut === 'absent') {
            $absenceId = $this->creerAbsenceDepuisSession($session, $dto, $userId);
        }

        if ($existing === null) {
            $presenceId = $this->repo->insertPresence([
                'appel_id'       => $sessionId,
                'eleve_id'       => $dto->eleveId,
                'statut'         => $dto->statut,
                'heure_arrivee'  => $dto->heureArrivee,
                'retard_minutes' => $dto->retardMinutes,
                'observation'    => $dto->observation,
                'absence_id'     => $absenceId,
                'saisie_par'     => $userId,
            ]);

            // Création automatique d'un retard dans vs_retards si statut = retard
            if ($dto->statut === 'retard') {
                $retardId = $this->creerRetardDepuisSession($session, $dto, $userId, $presenceId);
            }

            $this->dispatchStudentEvent($dto, $session, $sessionId, $absenceId, $retardId, $userId);

        } elseif ($existing['statut'] !== $dto->statut) {
            // Soft-delete du retard existant si le statut passe à autre chose
            if ((string)$existing['statut'] === 'retard') {
                $this->lateRepo->softDeleteByPresenceId((int)$existing['id']);
            }

            $this->repo->insertHistorique([
                'presence_id'    => (int)$existing['id'],
                'appel_id'       => $sessionId,
                'eleve_id'       => $dto->eleveId,
                'ancien_statut'  => $existing['statut'],
                'nouveau_statut' => $dto->statut,
                'motif'          => $dto->motifCorrection,
                'modifie_par'    => $userId,
            ]);

            $this->repo->updatePresence((int)$existing['id'], [
                'statut'         => $dto->statut,
                'heure_arrivee'  => $dto->heureArrivee,
                'retard_minutes' => $dto->retardMinutes,
                'observation'    => $dto->observation,
                'absence_id'     => $absenceId ?? $existing['absence_id'],
                'modifie_par'    => $userId,
            ]);

            // Créer le retard dans vs_retards si le nouveau statut est 'retard'
            if ($dto->statut === 'retard') {
                $retardId = $this->creerRetardDepuisSession($session, $dto, $userId, (int)$existing['id']);
            }

            $this->dispatchStudentEvent($dto, $session, $sessionId, $absenceId, $retardId, $userId);

        } else {
            $this->repo->updatePresence((int)$existing['id'], [
                'statut'         => $existing['statut'],
                'heure_arrivee'  => $dto->heureArrivee  ?? $existing['heure_arrivee'],
                'retard_minutes' => $dto->retardMinutes ?? $existing['retard_minutes'],
                'observation'    => $dto->observation   ?? $existing['observation'],
                'absence_id'     => $existing['absence_id'],
                'modifie_par'    => $userId,
            ]);
        }
    }

    /** Crée une entrée dans vs_absences liée à la session. Retourne l'ID créé. */
    private function creerAbsenceDepuisSession(array $session, PresenceDTO $dto, int $userId): int
    {
        return $this->absenceRepo->insert([
            'eleve_id'       => $dto->eleveId,
            'classe_id'      => $session['classe_id'],
            'annee_scolaire' => $session['annee_scolaire'],
            'date_absence'   => $session['date_appel'],
            'heure_debut'    => $session['heure_debut'],
            'heure_fin'      => $session['heure_fin'],
            'type'           => 'absence',
            'saisie_par'     => $userId,
            'observation'    => "Saisie automatique depuis appel #" . $session['id'],
        ]);
    }

    /** Crée une entrée dans vs_retards liée à la session. Retourne l'ID créé. */
    private function creerRetardDepuisSession(array $session, PresenceDTO $dto, int $userId, int $presenceId): int
    {
        return $this->lateRepo->insert([
            'eleve_id'       => $dto->eleveId,
            'classe_id'      => $session['classe_id'],
            'annee_scolaire' => $session['annee_scolaire'],
            'date_retard'    => $session['date_appel'],
            'heure_prevue'   => $session['heure_debut'] ?? null,
            'heure_arrivee'  => $dto->heureArrivee ?? date('H:i:s'),
            'duree_minutes'  => $dto->retardMinutes ?? 0,
            'appel_id'       => $session['id'],
            'presence_id'    => $presenceId,
            'saisie_par'     => $userId,
            'observation'    => "Saisie automatique depuis appel #{$session['id']}",
        ]);
    }

    private function dispatchStudentEvent(
        PresenceDTO $dto,
        array $session,
        int $sessionId,
        ?int $absenceId,
        ?int $retardId,
        int $userId
    ): void {
        $classeId      = (int)$session['classe_id'];
        $dateAppel     = $session['date_appel'];
        $anneeScolaire = $session['annee_scolaire'];

        if ($dto->statut === 'absent') {
            EventDispatcher::dispatch(new StudentAbsent(
                appelId:     $sessionId,
                eleveId:     $dto->eleveId,
                classeId:    $classeId,
                dateAppel:   $dateAppel,
                absenceId:   $absenceId,
                saisieParId: $userId,
            ));
        } elseif ($dto->statut === 'retard') {
            // Event Présences — audit de la session d'appel
            EventDispatcher::dispatch(new StudentLate(
                appelId:       $sessionId,
                eleveId:       $dto->eleveId,
                classeId:      $classeId,
                dateAppel:     $dateAppel,
                retardMinutes: $dto->retardMinutes,
                heureArrivee:  $dto->heureArrivee,
                saisieParId:   $userId,
            ));
            // Event Retards — seuil + notifications domaine Retards
            if ($retardId !== null) {
                EventDispatcher::dispatch(new RetardStudentLate(
                    retardId:      $retardId,
                    eleveId:       $dto->eleveId,
                    classeId:      $classeId,
                    dateRetard:    $dateAppel,
                    retardMinutes: $dto->retardMinutes ?? 0,
                    heureArrivee:  $dto->heureArrivee  ?? '',
                    anneeScolaire: $anneeScolaire,
                    saisieParId:   $userId,
                ));
            }
        } else {
            EventDispatcher::dispatch(new StudentPresent(
                appelId:     $sessionId,
                eleveId:     $dto->eleveId,
                classeId:    $classeId,
                dateAppel:   $dateAppel,
                saisieParId: $userId,
            ));
        }
    }
}

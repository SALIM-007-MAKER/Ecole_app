<?php

namespace App\Modules\VieScolaire\Presences\Repositories;

use App\Modules\VieScolaire\Presences\DTO\AttendanceFiltersDTO;
use Core\Database;
use PDO;

class AttendanceRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    // ── Sessions d'appel ─────────────────────────────────────────────────────

    public function findSessionById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT a.*,
                    c.nom                                 AS classe_nom,
                    c.niveau                              AS classe_niveau,
                    m.nom                                 AS matiere_nom,
                    CONCAT(u.prenom, ' ', u.nom)          AS enseignant_nom,
                    CONCAT(v.prenom, ' ', v.nom)          AS valide_par_nom
             FROM vs_appels a
             JOIN classes c ON c.id = a.classe_id
             LEFT JOIN matieres m ON m.id = a.matiere_id
             JOIN users u ON u.id = a.enseignant_id
             LEFT JOIN users v ON v.id = a.valide_par
             WHERE a.id = :id AND a.deleted_at IS NULL"
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function findSessions(AttendanceFiltersDTO $filters): array
    {
        [$where, $params] = $this->buildWhere($filters);
        $offset = ($filters->page - 1) * $filters->perPage;

        $sql = "SELECT a.*,
                       c.nom                        AS classe_nom,
                       m.nom                        AS matiere_nom,
                       CONCAT(u.prenom, ' ', u.nom) AS enseignant_nom,
                       COUNT(p.id)                  AS nb_pointes
                FROM vs_appels a
                JOIN classes c ON c.id = a.classe_id
                LEFT JOIN matieres m ON m.id = a.matiere_id
                JOIN users u ON u.id = a.enseignant_id
                LEFT JOIN vs_presences p ON p.appel_id = a.id
                WHERE {$where}
                GROUP BY a.id
                ORDER BY a.date_appel DESC, a.heure_debut DESC
                LIMIT :limit OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit',  $filters->perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset,            PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function countSessions(AttendanceFiltersDTO $filters): int
    {
        [$where, $params] = $this->buildWhere($filters);
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM vs_appels a WHERE {$where}"
        );
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public function findSessionByKey(int $classeId, string $date, ?string $heureDebut): ?array
    {
        if ($heureDebut === null) {
            $stmt = $this->pdo->prepare(
                "SELECT * FROM vs_appels
                 WHERE classe_id = :classe_id AND date_appel = :date
                   AND heure_debut IS NULL AND deleted_at IS NULL
                 LIMIT 1"
            );
            $stmt->execute([':classe_id' => $classeId, ':date' => $date]);
        } else {
            $stmt = $this->pdo->prepare(
                "SELECT * FROM vs_appels
                 WHERE classe_id = :classe_id AND date_appel = :date
                   AND heure_debut = :heure AND deleted_at IS NULL
                 LIMIT 1"
            );
            $stmt->execute([':classe_id' => $classeId, ':date' => $date, ':heure' => $heureDebut]);
        }
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function insertSession(array $data): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO vs_appels
                 (classe_id, matiere_id, enseignant_id, annee_scolaire,
                  date_appel, heure_debut, heure_fin, type_appel, observation)
             VALUES
                 (:classe_id, :matiere_id, :enseignant_id, :annee_scolaire,
                  :date_appel, :heure_debut, :heure_fin, :type_appel, :observation)"
        );
        $stmt->execute([
            ':classe_id'      => $data['classe_id'],
            ':matiere_id'     => $data['matiere_id']     ?? null,
            ':enseignant_id'  => $data['enseignant_id'],
            ':annee_scolaire' => $data['annee_scolaire'],
            ':date_appel'     => $data['date_appel'],
            ':heure_debut'    => $data['heure_debut']    ?? null,
            ':heure_fin'      => $data['heure_fin']      ?? null,
            ':type_appel'     => $data['type_appel']     ?? 'journalier',
            ':observation'    => $data['observation']    ?? null,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function validateSession(int $id, int $valideParId): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE vs_appels
             SET statut = 'valide', valide_par = :valide_par, valide_le = NOW(), updated_at = NOW()
             WHERE id = :id AND statut = 'brouillon' AND deleted_at IS NULL"
        );
        return $stmt->execute([':valide_par' => $valideParId, ':id' => $id]);
    }

    public function softDeleteSession(int $id): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE vs_appels SET deleted_at = NOW()
             WHERE id = :id AND statut = 'brouillon' AND deleted_at IS NULL"
        );
        return $stmt->execute([':id' => $id]);
    }

    // ── Pointage individuel ──────────────────────────────────────────────────

    public function findPresencesBySession(int $appelId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT p.*,
                    e.nom          AS eleve_nom,
                    e.prenom       AS eleve_prenom,
                    e.matricule,
                    CONCAT(u.prenom, ' ', u.nom) AS saisie_par_nom
             FROM vs_presences p
             JOIN eleves e ON e.id = p.eleve_id
             JOIN users  u ON u.id = p.saisie_par
             WHERE p.appel_id = :appel_id
             ORDER BY e.nom ASC, e.prenom ASC"
        );
        $stmt->execute([':appel_id' => $appelId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findPresenceByEleveAndSession(int $eleveId, int $appelId): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM vs_presences
             WHERE eleve_id = :eleve_id AND appel_id = :appel_id"
        );
        $stmt->execute([':eleve_id' => $eleveId, ':appel_id' => $appelId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function insertPresence(array $data): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO vs_presences
                 (appel_id, eleve_id, statut, heure_arrivee, retard_minutes,
                  observation, absence_id, saisie_par)
             VALUES
                 (:appel_id, :eleve_id, :statut, :heure_arrivee, :retard_minutes,
                  :observation, :absence_id, :saisie_par)"
        );
        $stmt->execute([
            ':appel_id'       => $data['appel_id'],
            ':eleve_id'       => $data['eleve_id'],
            ':statut'         => $data['statut'],
            ':heure_arrivee'  => $data['heure_arrivee']  ?? null,
            ':retard_minutes' => $data['retard_minutes']  ?? null,
            ':observation'    => $data['observation']     ?? null,
            ':absence_id'     => $data['absence_id']      ?? null,
            ':saisie_par'     => $data['saisie_par'],
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function updatePresence(int $id, array $data): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE vs_presences
             SET statut = :statut, heure_arrivee = :heure_arrivee,
                 retard_minutes = :retard_minutes, observation = :observation,
                 absence_id = :absence_id, modifie_par = :modifie_par,
                 updated_at = NOW()
             WHERE id = :id"
        );
        return $stmt->execute([
            ':statut'         => $data['statut'],
            ':heure_arrivee'  => $data['heure_arrivee']  ?? null,
            ':retard_minutes' => $data['retard_minutes']  ?? null,
            ':observation'    => $data['observation']     ?? null,
            ':absence_id'     => $data['absence_id']      ?? null,
            ':modifie_par'    => $data['modifie_par'],
            ':id'             => $id,
        ]);
    }

    public function updatePresenceAbsenceLink(int $presenceId, int $absenceId): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE vs_presences SET absence_id = :absence_id WHERE id = :id"
        );
        return $stmt->execute([':absence_id' => $absenceId, ':id' => $presenceId]);
    }

    // ── Historique ───────────────────────────────────────────────────────────

    public function insertHistorique(array $data): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO vs_presences_historique
                 (presence_id, appel_id, eleve_id, ancien_statut, nouveau_statut, motif, modifie_par)
             VALUES
                 (:presence_id, :appel_id, :eleve_id, :ancien_statut, :nouveau_statut, :motif, :modifie_par)"
        );
        $stmt->execute([
            ':presence_id'   => $data['presence_id'],
            ':appel_id'      => $data['appel_id'],
            ':eleve_id'      => $data['eleve_id'],
            ':ancien_statut' => $data['ancien_statut'],
            ':nouveau_statut'=> $data['nouveau_statut'],
            ':motif'         => $data['motif'] ?? null,
            ':modifie_par'   => $data['modifie_par'],
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function findHistoriqueBySession(int $appelId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT h.*,
                    CONCAT(e.prenom, ' ', e.nom) AS eleve_nom,
                    CONCAT(u.prenom, ' ', u.nom) AS modifie_par_nom
             FROM vs_presences_historique h
             JOIN eleves e ON e.id = h.eleve_id
             JOIN users  u ON u.id = h.modifie_par
             WHERE h.appel_id = :appel_id
             ORDER BY h.modifie_le DESC"
        );
        $stmt->execute([':appel_id' => $appelId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    // ── Statistiques ─────────────────────────────────────────────────────────

    public function statsParSession(int $appelId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT
                 COUNT(*)                                           AS total,
                 SUM(statut = 'present')                           AS presents,
                 SUM(statut = 'absent')                            AS absents,
                 SUM(statut = 'retard')                            AS retards,
                 SUM(statut = 'dispense')                          AS dispenses,
                 SUM(statut = 'sortie_anticipee')                  AS sorties_anticipees,
                 ROUND(SUM(statut = 'present') / COUNT(*) * 100, 1) AS taux_presence
             FROM vs_presences
             WHERE appel_id = :appel_id"
        );
        $stmt->execute([':appel_id' => $appelId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    public function statsParClasse(int $classeId, string $anneeScolaire): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT
                 e.id                             AS eleve_id,
                 CONCAT(e.prenom, ' ', e.nom)     AS eleve_nom,
                 COUNT(p.id)                       AS total_seances,
                 SUM(p.statut = 'present')         AS presents,
                 SUM(p.statut = 'absent')          AS absents,
                 SUM(p.statut = 'retard')          AS retards,
                 SUM(p.statut = 'dispense')        AS dispenses,
                 ROUND(SUM(p.statut = 'present') / NULLIF(COUNT(p.id), 0) * 100, 1) AS taux_presence
             FROM eleves e
             LEFT JOIN vs_presences p ON p.eleve_id = e.id
             LEFT JOIN vs_appels    a ON a.id = p.appel_id
                                     AND a.annee_scolaire = :annee
                                     AND a.deleted_at IS NULL
             WHERE e.classe_id = :classe_id AND e.actif = 1
             GROUP BY e.id
             ORDER BY e.nom ASC, e.prenom ASC"
        );
        $stmt->execute([':classe_id' => $classeId, ':annee' => $anneeScolaire]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    // ── Élèves d'une classe ──────────────────────────────────────────────────

    public function findElevesByClasse(int $classeId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, nom, prenom, matricule
             FROM eleves
             WHERE classe_id = :classe_id AND actif = 1
             ORDER BY nom ASC, prenom ASC"
        );
        $stmt->execute([':classe_id' => $classeId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    // ── Helpers privés ───────────────────────────────────────────────────────

    private function buildWhere(AttendanceFiltersDTO $filters): array
    {
        $conditions = ['a.deleted_at IS NULL'];
        $params     = [];

        if ($filters->classeId !== null) {
            $conditions[] = 'a.classe_id = :classe_id';
            $params[':classe_id'] = $filters->classeId;
        }
        if ($filters->enseignantId !== null) {
            $conditions[] = 'a.enseignant_id = :enseignant_id';
            $params[':enseignant_id'] = $filters->enseignantId;
        }
        if ($filters->anneeScolaire !== null) {
            $conditions[] = 'a.annee_scolaire = :annee';
            $params[':annee'] = $filters->anneeScolaire;
        }
        if ($filters->dateDebut !== null) {
            $conditions[] = 'a.date_appel >= :date_debut';
            $params[':date_debut'] = $filters->dateDebut;
        }
        if ($filters->dateFin !== null) {
            $conditions[] = 'a.date_appel <= :date_fin';
            $params[':date_fin'] = $filters->dateFin;
        }
        if ($filters->statut !== null) {
            $conditions[] = 'a.statut = :statut';
            $params[':statut'] = $filters->statut;
        }
        if ($filters->typeAppel !== null) {
            $conditions[] = 'a.type_appel = :type_appel';
            $params[':type_appel'] = $filters->typeAppel;
        }

        return [implode(' AND ', $conditions), $params];
    }
}

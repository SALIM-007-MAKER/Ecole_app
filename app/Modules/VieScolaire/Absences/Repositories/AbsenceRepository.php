<?php

namespace App\Modules\VieScolaire\Absences\Repositories;

use App\Modules\VieScolaire\Absences\DTO\AbsenceFiltersDTO;
use Core\Database;
use PDO;

class AbsenceRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    // ── Absences ──────────────────────────────────────────────────────────────

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT a.*, e.nom AS eleve_nom, e.prenom AS eleve_prenom, e.matricule,
                    c.nom AS classe_nom,
                    CONCAT(u.prenom, ' ', u.nom) AS saisie_par_nom
             FROM vs_absences a
             JOIN eleves  e ON e.id = a.eleve_id
             JOIN classes c ON c.id = a.classe_id
             JOIN users   u ON u.id = a.saisie_par
             WHERE a.id = :id AND a.deleted_at IS NULL"
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function findAll(AbsenceFiltersDTO $filters): array
    {
        [$where, $params] = $this->buildWhere($filters);

        $offset = ($filters->page - 1) * $filters->perPage;

        $sql = "SELECT a.*, e.nom AS eleve_nom, e.prenom AS eleve_prenom, e.matricule,
                       c.nom AS classe_nom,
                       CONCAT(u.prenom, ' ', u.nom) AS saisie_par_nom
                FROM vs_absences a
                JOIN eleves  e ON e.id = a.eleve_id
                JOIN classes c ON c.id = a.classe_id
                JOIN users   u ON u.id = a.saisie_par
                WHERE {$where}
                ORDER BY a.date_absence DESC, a.created_at DESC
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

    public function countAll(AbsenceFiltersDTO $filters): int
    {
        [$where, $params] = $this->buildWhere($filters);

        $sql = "SELECT COUNT(*) FROM vs_absences a WHERE {$where}";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public function findByEleveAndDate(int $eleveId, string $date): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM vs_absences
             WHERE eleve_id = :eleve_id AND date_absence = :date AND deleted_at IS NULL"
        );
        $stmt->execute([':eleve_id' => $eleveId, ':date' => $date]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Effectif d'une classe pour une date donnée, avec l'éventuelle absence
     * (vs_absences) et l'éventuel retard (vs_retards, domaine Retards) déjà
     * enregistrés ce jour-là. Alimente l'écran de pointage groupé.
     */
    public function findRosterForPointage(int $classeId, string $date): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT e.id AS eleve_id, e.nom, e.prenom, e.matricule,
                    a.id AS absence_id, a.type AS absence_type, a.observation AS absence_observation,
                    r.id AS retard_id, r.duree_minutes AS retard_duree, r.observation AS retard_observation
             FROM eleves e
             LEFT JOIN vs_absences a
                    ON a.eleve_id = e.id AND a.date_absence = :date_a AND a.classe_id = :classe_a
                   AND a.deleted_at IS NULL AND a.type IN ('absence','dispense')
             LEFT JOIN vs_retards r
                    ON r.eleve_id = e.id AND r.date_retard = :date_r AND r.classe_id = :classe_r
                   AND r.deleted_at IS NULL
             WHERE e.classe_id = :classe_w AND e.actif = 1
             ORDER BY e.nom, e.prenom"
        );
        $stmt->execute([
            ':date_a' => $date, ':classe_a' => $classeId,
            ':date_r' => $date, ':classe_r' => $classeId,
            ':classe_w' => $classeId,
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function insert(array $data): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO vs_absences
                 (eleve_id, classe_id, annee_scolaire, date_absence,
                  heure_debut, heure_fin, duree_heures, type, statut,
                  saisie_par, observation)
             VALUES
                 (:eleve_id, :classe_id, :annee_scolaire, :date_absence,
                  :heure_debut, :heure_fin, :duree_heures, :type, 'non_justifiee',
                  :saisie_par, :observation)"
        );
        $stmt->execute([
            ':eleve_id'      => $data['eleve_id'],
            ':classe_id'     => $data['classe_id'],
            ':annee_scolaire'=> $data['annee_scolaire'],
            ':date_absence'  => $data['date_absence'],
            ':heure_debut'   => $data['heure_debut']   ?? null,
            ':heure_fin'     => $data['heure_fin']     ?? null,
            ':duree_heures'  => $data['duree_heures']  ?? null,
            ':type'          => $data['type'],
            ':saisie_par'    => $data['saisie_par'],
            ':observation'   => $data['observation']   ?? null,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function updateStatut(int $id, string $statut): bool
    {
        $allowed = ['non_justifiee', 'en_attente', 'justifiee', 'refusee'];
        if (!in_array($statut, $allowed, true)) {
            return false;
        }
        $stmt = $this->pdo->prepare(
            "UPDATE vs_absences SET statut = :statut, updated_at = NOW()
             WHERE id = :id AND deleted_at IS NULL"
        );
        return $stmt->execute([':statut' => $statut, ':id' => $id]);
    }

    public function softDelete(int $id): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE vs_absences SET deleted_at = NOW()
             WHERE id = :id AND deleted_at IS NULL"
        );
        return $stmt->execute([':id' => $id]);
    }

    // ── Statistiques ──────────────────────────────────────────────────────────

    public function countByEleveAndAnnee(int $eleveId, string $anneeScolaire): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT
                 SUM(type = 'absence')  AS total_absences,
                 SUM(type = 'retard')   AS total_retards,
                 SUM(type = 'dispense') AS total_dispenses,
                 SUM(statut = 'non_justifiee' AND type = 'absence') AS absences_non_justifiees,
                 SUM(statut = 'justifiee'     AND type = 'absence') AS absences_justifiees,
                 IFNULL(SUM(duree_heures), 0) AS total_heures
             FROM vs_absences
             WHERE eleve_id = :eleve_id
               AND annee_scolaire = :annee
               AND deleted_at IS NULL"
        );
        $stmt->execute([':eleve_id' => $eleveId, ':annee' => $anneeScolaire]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Compte les absences justifiées/non justifiées d'un élève sur une plage
     * de dates (ex : les dates d'une période scolaire — utilisé par le
     * bulletin V1, cf. BulletinGenerator).
     *
     * @return array{justifiees: int, nonJustifiees: int}
     */
    public function countByEleveAndDateRange(int $eleveId, string $dateDebut, string $dateFin): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT
                 IFNULL(SUM(statut = 'justifiee'     AND type = 'absence'), 0) AS justifiees,
                 IFNULL(SUM(statut = 'non_justifiee' AND type = 'absence'), 0) AS non_justifiees
             FROM vs_absences
             WHERE eleve_id = :eleve_id
               AND date_absence BETWEEN :date_debut AND :date_fin
               AND deleted_at IS NULL"
        );
        $stmt->execute([
            ':eleve_id'   => $eleveId,
            ':date_debut' => $dateDebut,
            ':date_fin'   => $dateFin,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'justifiees'    => (int)($row['justifiees']     ?? 0),
            'nonJustifiees' => (int)($row['non_justifiees']  ?? 0),
        ];
    }

    public function statsByClasse(int $classeId, string $anneeScolaire): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT e.id AS eleve_id,
                    CONCAT(e.prenom, ' ', e.nom) AS eleve_nom,
                    COUNT(*) AS total,
                    SUM(a.type = 'absence') AS absences,
                    SUM(a.statut = 'non_justifiee' AND a.type = 'absence') AS non_justifiees
             FROM vs_absences a
             JOIN eleves e ON e.id = a.eleve_id
             WHERE a.classe_id = :classe_id
               AND a.annee_scolaire = :annee
               AND a.deleted_at IS NULL
             GROUP BY e.id
             ORDER BY non_justifiees DESC, total DESC"
        );
        $stmt->execute([':classe_id' => $classeId, ':annee' => $anneeScolaire]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    // ── Justifications ────────────────────────────────────────────────────────

    public function findJustificationByAbsence(int $absenceId): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT j.*, m.libelle AS motif_libelle, m.categorie AS motif_categorie
             FROM vs_justifications_absences j
             LEFT JOIN vs_motifs_absence m ON m.id = j.motif_id
             WHERE j.absence_id = :absence_id"
        );
        $stmt->execute([':absence_id' => $absenceId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function insertJustification(array $data): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO vs_justifications_absences
                 (absence_id, motif_id, description, fichier_justificatif, statut, soumis_par)
             VALUES
                 (:absence_id, :motif_id, :description, :fichier, 'en_attente', :soumis_par)"
        );
        $stmt->execute([
            ':absence_id' => $data['absence_id'],
            ':motif_id'   => $data['motif_id']   ?? null,
            ':description'=> $data['description'] ?? null,
            ':fichier'    => $data['fichier']     ?? null,
            ':soumis_par' => $data['soumis_par'],
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function validerJustification(int $justificationId, int $valideParId): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE vs_justifications_absences
             SET statut = 'validee', valide_par = :valide_par, valide_le = NOW()
             WHERE id = :id AND statut = 'en_attente'"
        );
        return $stmt->execute([':valide_par' => $valideParId, ':id' => $justificationId]);
    }

    public function refuserJustification(int $justificationId, int $rejeteParId, string $motifRefus): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE vs_justifications_absences
             SET statut = 'refusee', valide_par = :rejete_par, valide_le = NOW(),
                 motif_refus = :motif_refus
             WHERE id = :id AND statut = 'en_attente'"
        );
        return $stmt->execute([
            ':rejete_par'  => $rejeteParId,
            ':id'          => $justificationId,
            ':motif_refus' => $motifRefus,
        ]);
    }

    // ── Motifs ────────────────────────────────────────────────────────────────

    public function findAllMotifs(): array
    {
        return $this->pdo->query(
            "SELECT * FROM vs_motifs_absence WHERE actif = 1 ORDER BY libelle ASC"
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function buildWhere(AbsenceFiltersDTO $filters): array
    {
        $conditions = ['a.deleted_at IS NULL'];
        $params     = [];

        if ($filters->eleveId !== null) {
            $conditions[] = 'a.eleve_id = :eleve_id';
            $params[':eleve_id'] = $filters->eleveId;
        }
        if ($filters->eleveIds !== null) {
            if (empty($filters->eleveIds)) {
                $conditions[] = '1 = 0';
            } else {
                $placeholders = [];
                foreach (array_values($filters->eleveIds) as $i => $eid) {
                    $key = ":scope_eleve_{$i}";
                    $placeholders[] = $key;
                    $params[$key] = $eid;
                }
                $conditions[] = 'a.eleve_id IN (' . implode(',', $placeholders) . ')';
            }
        }
        if ($filters->classeId !== null) {
            $conditions[] = 'a.classe_id = :classe_id';
            $params[':classe_id'] = $filters->classeId;
        }
        if ($filters->anneeScolaire !== null) {
            $conditions[] = 'a.annee_scolaire = :annee';
            $params[':annee'] = $filters->anneeScolaire;
        }
        if ($filters->dateDebut !== null) {
            $conditions[] = 'a.date_absence >= :date_debut';
            $params[':date_debut'] = $filters->dateDebut;
        }
        if ($filters->dateFin !== null) {
            $conditions[] = 'a.date_absence <= :date_fin';
            $params[':date_fin'] = $filters->dateFin;
        }
        if ($filters->type !== null) {
            $conditions[] = 'a.type = :type';
            $params[':type'] = $filters->type;
        }
        if ($filters->statut !== null) {
            $conditions[] = 'a.statut = :statut';
            $params[':statut'] = $filters->statut;
        }

        return [implode(' AND ', $conditions), $params];
    }
}

<?php

namespace App\Modules\RH\Enseignants\Repositories;

use App\Modules\RH\Enseignants\DTO\TeacherFiltersDTO;
use Core\Database;
use PDO;

class TeacherRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    // ── Lecture ───────────────────────────────────────────────────────────────

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT t.*,
                    e.matricule, e.nom, e.prenom, e.email_pro, e.telephone,
                    e.type_personnel, e.statut AS statut_employe,
                    e.departement_id, e.poste_id,
                    d.nom  AS departement_nom,
                    p.intitule AS poste_intitule,
                    u.email AS user_email
             FROM rh_enseignants t
             INNER JOIN rh_employes    e ON e.id = t.employe_id
             LEFT  JOIN rh_departements d ON d.id = e.departement_id
             LEFT  JOIN rh_postes       p ON p.id = e.poste_id
             LEFT  JOIN users           u ON u.id = e.user_id
             WHERE t.id = :id AND t.deleted_at IS NULL"
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function findByIdIncludingArchived(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT t.*, e.matricule, e.nom, e.prenom, e.email_pro
             FROM rh_enseignants t
             INNER JOIN rh_employes e ON e.id = t.employe_id
             WHERE t.id = :id"
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function findByEmployeId(int $employeId): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT t.*, e.matricule, e.nom, e.prenom
             FROM rh_enseignants t
             INNER JOIN rh_employes e ON e.id = t.employe_id
             WHERE t.employe_id = :eid AND t.deleted_at IS NULL"
        );
        $stmt->execute([':eid' => $employeId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function findAll(TeacherFiltersDTO $f): array
    {
        [$where, $params] = $this->buildWhere($f);
        $offset = ($f->page - 1) * $f->perPage;

        $sql = "SELECT t.*,
                       e.matricule, e.nom, e.prenom, e.email_pro, e.statut AS statut_employe,
                       d.nom AS departement_nom,
                       COUNT(DISTINCT em.matiere_id) AS nb_matieres
                FROM rh_enseignants t
                INNER JOIN rh_employes     e  ON e.id = t.employe_id
                LEFT  JOIN rh_departements d  ON d.id = e.departement_id
                LEFT  JOIN rh_enseignant_matieres em ON em.enseignant_id = t.id AND em.actif = 1
                WHERE {$where}
                GROUP BY t.id
                ORDER BY e.nom ASC, e.prenom ASC
                LIMIT :limit OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit',  $f->perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset,     PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countAll(TeacherFiltersDTO $f): int
    {
        [$where, $params] = $this->buildWhere($f);
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(DISTINCT t.id)
             FROM rh_enseignants t
             INNER JOIN rh_employes e ON e.id = t.employe_id
             WHERE {$where}"
        );
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public function employeHasProfile(int $employeId, int $excludeId = 0): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT id FROM rh_enseignants
             WHERE employe_id = :eid AND id != :excl AND deleted_at IS NULL"
        );
        $stmt->execute([':eid' => $employeId, ':excl' => $excludeId]);
        return $stmt->fetchColumn() !== false;
    }

    // ── Matières ──────────────────────────────────────────────────────────────

    public function findMatieres(int $enseignantId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT em.*, m.nom AS matiere_nom, m.coefficient
             FROM rh_enseignant_matieres em
             INNER JOIN matieres m ON m.id = em.matiere_id
             WHERE em.enseignant_id = :eid
             ORDER BY em.priorite ASC, m.nom ASC"
        );
        $stmt->execute([':eid' => $enseignantId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function deleteMatieres(int $enseignantId): void
    {
        $stmt = $this->pdo->prepare(
            "DELETE FROM rh_enseignant_matieres WHERE enseignant_id = :eid"
        );
        $stmt->execute([':eid' => $enseignantId]);
    }

    public function insertMatiere(int $enseignantId, int $matiereId, ?string $niveaux, int $priorite): void
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO rh_enseignant_matieres (enseignant_id, matiere_id, niveaux, priorite, actif)
             VALUES (:eid, :mid, :niv, :prio, 1)
             ON DUPLICATE KEY UPDATE niveaux = VALUES(niveaux), priorite = VALUES(priorite), actif = 1"
        );
        $stmt->execute([':eid' => $enseignantId, ':mid' => $matiereId, ':niv' => $niveaux, ':prio' => $priorite]);
    }

    public function updateChargeActuelle(int $enseignantId): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE rh_enseignants t
             SET t.charge_horaire_actuelle = (
                 SELECT COALESCE(SUM(m.volume_horaire), 0)
                 FROM rh_enseignant_matieres em
                 INNER JOIN matieres m ON m.id = em.matiere_id
                 WHERE em.enseignant_id = :eid AND em.actif = 1
             ),
             t.updated_at = NOW()
             WHERE t.id = :eid"
        );
        $stmt->execute([':eid' => $enseignantId]);
    }

    // ── Qualifications ────────────────────────────────────────────────────────

    public function findQualifications(int $enseignantId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM rh_enseignant_qualifications
             WHERE enseignant_id = :eid
             ORDER BY date_obtention DESC, created_at DESC"
        );
        $stmt->execute([':eid' => $enseignantId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function insertQualification(int $enseignantId, array $data): int
    {
        $data['enseignant_id'] = $enseignantId;
        $cols = implode(', ', array_map(fn($k) => "`{$k}`", array_keys($data)));
        $phs  = implode(', ', array_map(fn($k) => ":{$k}", array_keys($data)));
        $stmt = $this->pdo->prepare(
            "INSERT INTO rh_enseignant_qualifications ({$cols}) VALUES ({$phs})"
        );
        $stmt->execute($data);
        return (int)$this->pdo->lastInsertId();
    }

    public function deleteQualification(int $qualId, int $enseignantId): void
    {
        $stmt = $this->pdo->prepare(
            "DELETE FROM rh_enseignant_qualifications WHERE id = :id AND enseignant_id = :eid"
        );
        $stmt->execute([':id' => $qualId, ':eid' => $enseignantId]);
    }

    // ── Référentiels ──────────────────────────────────────────────────────────

    public function findMatieresPourSelect(): array
    {
        $stmt = $this->pdo->query(
            "SELECT id, nom, coefficient FROM matieres ORDER BY nom"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findEmployesSansProfile(): array
    {
        $stmt = $this->pdo->query(
            "SELECT e.id, e.matricule,
                    CONCAT(e.prenom, ' ', e.nom, ' [', e.matricule, ']') AS label,
                    e.type_personnel
             FROM rh_employes e
             WHERE e.deleted_at IS NULL
               AND e.statut = 'actif'
               AND NOT EXISTS (
                   SELECT 1 FROM rh_enseignants t
                   WHERE t.employe_id = e.id AND t.deleted_at IS NULL
               )
             ORDER BY e.nom, e.prenom"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── Statistiques ──────────────────────────────────────────────────────────

    public function countByStatut(): array
    {
        $stmt = $this->pdo->query(
            "SELECT statut_pedagogique, COUNT(*) AS total
             FROM rh_enseignants
             WHERE deleted_at IS NULL
             GROUP BY statut_pedagogique"
        );
        return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    public function countByMatiere(): array
    {
        $stmt = $this->pdo->query(
            "SELECT m.nom AS matiere, COUNT(em.enseignant_id) AS total
             FROM rh_enseignant_matieres em
             INNER JOIN matieres m ON m.id = em.matiere_id
             INNER JOIN rh_enseignants t ON t.id = em.enseignant_id AND t.deleted_at IS NULL
             WHERE em.actif = 1
             GROUP BY em.matiere_id, m.nom
             ORDER BY total DESC"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countArchives(): int
    {
        $stmt = $this->pdo->query(
            "SELECT COUNT(*) FROM rh_enseignants WHERE deleted_at IS NOT NULL"
        );
        return (int)$stmt->fetchColumn();
    }

    // ── Écriture ──────────────────────────────────────────────────────────────

    public function insert(array $data): int
    {
        $cols = implode(', ', array_map(fn($k) => "`{$k}`", array_keys($data)));
        $phs  = implode(', ', array_map(fn($k) => ":{$k}", array_keys($data)));
        $stmt = $this->pdo->prepare("INSERT INTO rh_enseignants ({$cols}) VALUES ({$phs})");
        $stmt->execute($data);
        return (int)$this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $sets = implode(', ', array_map(fn($k) => "`{$k}` = :{$k}", array_keys($data)));
        $stmt = $this->pdo->prepare(
            "UPDATE rh_enseignants SET {$sets}, updated_at = NOW() WHERE id = :_id"
        );
        $data['_id'] = $id;
        $stmt->execute($data);
    }

    public function softDelete(int $id): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE rh_enseignants SET deleted_at = NOW(), updated_at = NOW() WHERE id = :id"
        );
        $stmt->execute([':id' => $id]);
    }

    public function restore(int $id): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE rh_enseignants SET deleted_at = NULL, updated_at = NOW() WHERE id = :id"
        );
        $stmt->execute([':id' => $id]);
    }

    // ── Privé ─────────────────────────────────────────────────────────────────

    private function buildWhere(TeacherFiltersDTO $f): array
    {
        $conds  = [];
        $params = [];

        if ($f->includeArch !== '1') {
            $conds[] = 't.deleted_at IS NULL';
        }

        if ($f->q !== '') {
            $conds[]    = '(e.nom LIKE :q OR e.prenom LIKE :q OR e.matricule LIKE :q OR e.email_pro LIKE :q OR t.specialite_principale LIKE :q)';
            $params[':q'] = '%' . $f->q . '%';
        }

        if ($f->statut !== '') {
            $conds[]             = 't.statut_pedagogique = :statut';
            $params[':statut']   = $f->statut;
        }

        if ($f->matiereId > 0) {
            $conds[]              = 'EXISTS (SELECT 1 FROM rh_enseignant_matieres em WHERE em.enseignant_id = t.id AND em.matiere_id = :mid AND em.actif = 1)';
            $params[':mid']       = $f->matiereId;
        }

        return [
            $conds ? implode(' AND ', $conds) : '1=1',
            $params,
        ];
    }
}

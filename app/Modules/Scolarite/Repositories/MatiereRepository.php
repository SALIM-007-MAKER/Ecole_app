<?php

namespace App\Modules\Scolarite\Repositories;

use Core\Database;
use PDO;

class MatiereRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    // ─── Listing paginé ───────────────────────────────────────────────────────

    public function paginate(
        string $q       = '',
        string $actif   = '1',
        string $filiere = '',
        string $niveau  = '',
        int    $page    = 1,
        int    $perPage = 25
    ): array {
        $where  = [];
        $params = [];

        if ($q !== '') {
            $where[]  = '(m.nom LIKE ? OR m.description LIKE ?)';
            $like     = '%' . $q . '%';
            $params   = array_merge($params, [$like, $like]);
        }
        if ($actif !== '') {
            $where[]  = 'm.actif = ?';
            $params[] = (int)$actif;
        }
        if ($filiere !== '') {
            $where[]  = 'm.filiere = ?';
            $params[] = $filiere;
        }
        if ($niveau !== '') {
            $where[]  = 'FIND_IN_SET(?, m.niveaux)';
            $params[] = $niveau;
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $offset   = ($page - 1) * $perPage;

        $stmtCount = $this->pdo->prepare(
            "SELECT COUNT(*) FROM `matieres` m $whereSql"
        );
        $stmtCount->execute($params);
        $total = (int)$stmtCount->fetchColumn();

        $stmtData = $this->pdo->prepare(
            "SELECT m.*,
                    CONCAT(p.prenom, ' ', p.nom) AS responsable_nom,
                    COUNT(DISTINCT en.id)         AS nb_enseignements
             FROM `matieres` m
             LEFT JOIN `professeurs`   p  ON p.id  = m.responsable_id
             LEFT JOIN `enseignements` en ON en.matiere_id = m.id
             $whereSql
             GROUP BY m.id
             ORDER BY m.nom ASC
             LIMIT $perPage OFFSET $offset"
        );
        $stmtData->execute($params);
        $data = $stmtData->fetchAll(PDO::FETCH_OBJ);

        return [
            'data'        => $data,
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $perPage,
            'total_pages' => $total > 0 ? (int)ceil($total / $perPage) : 1,
        ];
    }

    // ─── Détail ───────────────────────────────────────────────────────────────

    public function findWithDetails(int $id): ?\stdClass
    {
        $stmt = $this->pdo->prepare(
            "SELECT m.*,
                    CONCAT(p.prenom, ' ', p.nom) AS responsable_nom,
                    p.specialite                 AS responsable_specialite,
                    p.telephone                  AS responsable_telephone,
                    COUNT(DISTINCT en.id)         AS nb_enseignements
             FROM `matieres` m
             LEFT JOIN `professeurs`   p  ON p.id  = m.responsable_id
             LEFT JOIN `enseignements` en ON en.matiere_id = m.id
             WHERE m.id = ?
             GROUP BY m.id"
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_OBJ);
        return $row ?: null;
    }

    // ─── Enseignements d'une matière ─────────────────────────────────────────

    public function getEnseignements(int $matiereId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT e.*,
                    CONCAT(p.prenom, ' ', p.nom) AS prof_nom,
                    p.specialite,
                    c.nom   AS classe_nom,
                    c.niveau AS classe_niveau
             FROM `enseignements` e
             INNER JOIN `professeurs` p ON p.id = e.professeur_id
             INNER JOIN `classes`     c ON c.id = e.classe_id
             WHERE e.matiere_id = ?
             ORDER BY e.annee_scolaire DESC, c.niveau, c.nom, p.nom"
        );
        $stmt->execute([$matiereId]);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    // ─── Pour les selects ─────────────────────────────────────────────────────

    public function findForSelect(?string $niveau = null): array
    {
        $where  = 'm.actif = 1';
        $params = [];

        if ($niveau !== null && $niveau !== '') {
            $where   .= ' AND FIND_IN_SET(?, m.niveaux)';
            $params[] = $niveau;
        }

        $stmt = $this->pdo->prepare(
            "SELECT m.id,
                    CONCAT(m.nom, ' (coef. ', m.coefficient, ')') AS label,
                    m.nom, m.coefficient, m.volume_horaire, m.couleur
             FROM `matieres` m
             WHERE $where
             ORDER BY m.nom"
        );
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    // ─── Stats ────────────────────────────────────────────────────────────────

    public function countStats(): array
    {
        $row = $this->pdo->query(
            "SELECT
                COUNT(*)           AS total,
                SUM(actif = 1)     AS actives,
                SUM(actif = 0)     AS archivees,
                SUM(coefficient)   AS total_coef,
                SUM(volume_horaire)AS total_heures
             FROM `matieres`"
        )->fetch(PDO::FETCH_OBJ);

        $nbEns = (int)$this->pdo->query(
            "SELECT COUNT(DISTINCT id) FROM `enseignements`"
        )->fetchColumn();

        return [
            'total'        => (int)($row->total ?? 0),
            'actives'      => (int)($row->actives ?? 0),
            'archivees'    => (int)($row->archivees ?? 0),
            'total_coef'   => (float)($row->total_coef ?? 0),
            'total_heures' => (int)($row->total_heures ?? 0),
            'nb_enseignements' => $nbEns,
        ];
    }

    // ─── Filieres disponibles ─────────────────────────────────────────────────

    public function getFilieres(): array
    {
        $stmt = $this->pdo->query(
            "SELECT DISTINCT filiere FROM `matieres`
             WHERE filiere IS NOT NULL AND filiere != ''
             ORDER BY filiere"
        );
        return array_column($stmt->fetchAll(PDO::FETCH_NUM), 0);
    }

    // ─── Contrainte unicité ───────────────────────────────────────────────────

    public function nomExists(string $nom, int $excludeId = 0): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM `matieres` WHERE nom = ? AND id != ?"
        );
        $stmt->execute([$nom, $excludeId]);
        return (int)$stmt->fetchColumn() > 0;
    }

    // ─── Vérifier si supprimable ─────────────────────────────────────────────

    public function countEnseignements(int $matiereId): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM `enseignements` WHERE matiere_id = ?"
        );
        $stmt->execute([$matiereId]);
        return (int)$stmt->fetchColumn();
    }

    public function countNotes(int $matiereId): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM `notes` WHERE matiere_id = ?"
        );
        $stmt->execute([$matiereId]);
        return (int)$stmt->fetchColumn();
    }
}

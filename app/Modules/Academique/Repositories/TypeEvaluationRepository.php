<?php

namespace App\Modules\Academique\Repositories;

use Core\Database;
use PDO;

class TypeEvaluationRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    // ─── Lecture ─────────────────────────────────────────────────────────────

    public function paginate(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        [$where, $params] = $this->buildWhere($filters);

        $countStmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM `types_evaluations` te {$where}"
        );
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $offset = ($page - 1) * $perPage;
        $stmt   = $this->pdo->prepare(
            "SELECT te.*,
                    (SELECT COUNT(*) FROM `controles` c WHERE c.type = te.code) AS nb_controles_v1
             FROM `types_evaluations` te
             {$where}
             ORDER BY te.est_archive ASC, te.actif DESC, te.ordre ASC, te.nom ASC
             LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute($params);

        return [
            'data'         => $stmt->fetchAll(PDO::FETCH_OBJ),
            'total'        => $total,
            'per_page'     => $perPage,
            'current_page' => $page,
            'last_page'    => $total > 0 ? (int)ceil($total / $perPage) : 1,
        ];
    }

    public function findWithStats(int $id): ?\stdClass
    {
        $stmt = $this->pdo->prepare(
            "SELECT te.*,
                    (SELECT COUNT(*) FROM `controles` c WHERE c.type = te.code) AS nb_controles_v1
             FROM `types_evaluations` te
             WHERE te.id = ?"
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_OBJ);
        return $row ?: null;
    }

    public function countStats(): array
    {
        $row = $this->pdo->query(
            "SELECT
                COUNT(*)                      AS total,
                SUM(actif = 1 AND est_archive = 0)  AS actifs,
                SUM(actif = 0 AND est_archive = 0)  AS inactifs,
                SUM(est_archive = 1)          AS archives,
                SUM(est_systeme = 1)          AS systeme
             FROM `types_evaluations`"
        )->fetch(PDO::FETCH_OBJ);

        return [
            'total'    => (int)($row->total    ?? 0),
            'actifs'   => (int)($row->actifs   ?? 0),
            'inactifs' => (int)($row->inactifs ?? 0),
            'archives' => (int)($row->archives ?? 0),
            'systeme'  => (int)($row->systeme  ?? 0),
        ];
    }

    public function codeExists(string $code, int $excludeId = 0): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM `types_evaluations` WHERE code = ? AND id != ?"
        );
        $stmt->execute([$code, $excludeId]);
        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Vérifie si un type est utilisé dans les évaluations existantes.
     * Vérifie V1 (controles.type = code) et V2 (evaluations.type_evaluation_id) si la table existe.
     */
    public function isUsed(int $id, string $code): bool
    {
        // Vérification V1 : controles.type est un ENUM string
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM `controles` WHERE `type` = ?"
        );
        $stmt->execute([$code]);
        if ((int)$stmt->fetchColumn() > 0) {
            return true;
        }

        // Vérification V2 : table evaluations (créée en phase 2.3)
        try {
            $stmt = $this->pdo->prepare(
                "SELECT COUNT(*) FROM `evaluations` WHERE `type_evaluation_id` = ?"
            );
            $stmt->execute([$id]);
            if ((int)$stmt->fetchColumn() > 0) {
                return true;
            }
        } catch (\Exception $e) {
            // Table evaluations inexistante (migration ultérieure)
        }

        return false;
    }

    public function listActifs(): array
    {
        $stmt = $this->pdo->query(
            "SELECT id, code, nom, coefficient_defaut, note_max_defaut,
                    est_eliminatoire, seuil_eliminatoire, couleur, icone, ordre
             FROM `types_evaluations`
             WHERE actif = 1 AND est_archive = 0
             ORDER BY ordre ASC, nom ASC"
        );
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    // ─── Helper privé ────────────────────────────────────────────────────────

    private function buildWhere(array $filters): array
    {
        $conditions = [];
        $params     = [];

        if (!empty($filters['search'])) {
            $conditions[] = '(te.nom LIKE ? OR te.code LIKE ? OR te.description LIKE ?)';
            $like         = '%' . $filters['search'] . '%';
            $params[]     = $like;
            $params[]     = $like;
            $params[]     = $like;
        }

        if ($filters['actif'] !== '' && $filters['actif'] !== null) {
            if ($filters['actif'] === '1') {
                $conditions[] = 'te.actif = 1 AND te.est_archive = 0';
            } elseif ($filters['actif'] === '0') {
                $conditions[] = 'te.actif = 0 AND te.est_archive = 0';
            } elseif ($filters['actif'] === 'archive') {
                $conditions[] = 'te.est_archive = 1';
            }
        }

        if (!empty($filters['systeme']) && $filters['systeme'] === '1') {
            $conditions[] = 'te.est_systeme = 1';
        }

        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
        return [$where, $params];
    }
}

<?php

namespace App\Modules\Scolarite\Repositories;

use Core\Database;
use PDO;

class EleveRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    // ── Requête de base ───────────────────────────────────────────────────────

    private function baseSelect(): string
    {
        return "SELECT e.*,
                c.nom    AS classe_nom,
                c.niveau AS classe_niveau,
                TRIM(CONCAT(COALESCE(u.prenom,''), ' ', COALESCE(u.nom,''))) AS parent_nom,
                u.telephone AS parent_telephone,
                u.email     AS parent_email";
    }

    private function baseFrom(): string
    {
        return "FROM `eleves` e
                LEFT JOIN `classes` c ON c.id = e.classe_id
                LEFT JOIN `users`   u ON u.id = e.parent_id";
    }

    private function buildWhere(array $filters): array
    {
        $conditions = [];
        $params     = [];

        if (!empty($filters['q'])) {
            $term = '%' . $filters['q'] . '%';
            $conditions[] = "(e.nom LIKE ? OR e.prenom LIKE ? OR e.matricule LIKE ? OR e.telephone LIKE ?)";
            array_push($params, $term, $term, $term, $term);
        }

        if (!empty($filters['classe_id'])) {
            $conditions[] = "e.classe_id = ?";
            $params[] = (int)$filters['classe_id'];
        }

        if (in_array($filters['sexe'] ?? '', ['M', 'F'], true)) {
            $conditions[] = "e.sexe = ?";
            $params[] = $filters['sexe'];
        }

        $actif = $filters['actif'] ?? '1';
        if ($actif !== 'all') {
            $conditions[] = "e.actif = ?";
            $params[] = (int)$actif;
        }

        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
        return [$where, $params];
    }

    // ── Lecture ───────────────────────────────────────────────────────────────

    public function paginate(array $filters = [], int $page = 1, int $perPage = 25): array
    {
        [$where, $params] = $this->buildWhere($filters);
        $from  = $this->baseFrom();
        $order = "ORDER BY e.nom ASC, e.prenom ASC";

        $countStmt = $this->pdo->prepare("SELECT COUNT(*) {$from} {$where}");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $offset   = ($page - 1) * $perPage;
        $dataStmt = $this->pdo->prepare(
            $this->baseSelect() . " {$from} {$where} {$order} LIMIT {$perPage} OFFSET {$offset}"
        );
        $dataStmt->execute($params);

        return [
            'data'         => $dataStmt->fetchAll(),
            'total'        => $total,
            'per_page'     => $perPage,
            'current_page' => $page,
            'last_page'    => $total > 0 ? (int)ceil($total / $perPage) : 1,
        ];
    }

    public function findAllFiltered(array $filters = []): array
    {
        [$where, $params] = $this->buildWhere($filters);
        $stmt = $this->pdo->prepare(
            $this->baseSelect() . ' ' . $this->baseFrom() . " {$where} ORDER BY e.nom ASC, e.prenom ASC"
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function findWithDetails(int $eleveId): object|false
    {
        $stmt = $this->pdo->prepare(
            $this->baseSelect() . ' ' . $this->baseFrom() . ' WHERE e.id = ?'
        );
        $stmt->execute([$eleveId]);
        return $stmt->fetch();
    }

    public function search(string $term, int $limit = 20): array
    {
        $like = '%' . $term . '%';
        $stmt = $this->pdo->prepare(
            $this->baseSelect() . ' ' . $this->baseFrom() .
            " WHERE (e.nom LIKE ? OR e.prenom LIKE ? OR e.matricule LIKE ?) AND e.actif = 1
              ORDER BY e.nom ASC, e.prenom ASC LIMIT ?"
        );
        $stmt->execute([$like, $like, $like, $limit]);
        return $stmt->fetchAll();
    }

    public function listByClasseWithMoyennes(int $classeId, int $periodeId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT e.id, e.nom, e.prenom, e.matricule, e.sexe,
                    mg.moyenne, mg.rang
             FROM eleves e
             LEFT JOIN moyennes_generales mg ON mg.eleve_id = e.id AND mg.periode_id = ?
             WHERE e.classe_id = ? AND e.actif = 1
             ORDER BY mg.rang ASC, e.nom ASC"
        );
        $stmt->execute([$periodeId, $classeId]);
        return $stmt->fetchAll();
    }

    public function listSansClasse(string $anneeScolaire = ''): array
    {
        $stmt = $this->pdo->prepare(
            $this->baseSelect() . ' ' . $this->baseFrom() .
            " WHERE e.classe_id IS NULL AND e.actif = 1 ORDER BY e.nom ASC, e.prenom ASC"
        );
        $stmt->execute([]);
        return $stmt->fetchAll();
    }

    public function findDoublons(): array
    {
        $stmt = $this->pdo->query(
            "SELECT nom, prenom, date_naissance, COUNT(*) as nb
             FROM eleves
             GROUP BY nom, prenom, date_naissance
             HAVING COUNT(*) > 1
             ORDER BY nb DESC, nom ASC"
        );
        return $stmt->fetchAll();
    }

    public function countStats(): array
    {
        $stmt = $this->pdo->query(
            "SELECT
                COUNT(*)                              AS total,
                SUM(actif = 1)                        AS actifs,
                SUM(actif = 0)                        AS inactifs,
                SUM(actif = 1 AND sexe = 'M')        AS garcons,
                SUM(actif = 1 AND sexe = 'F')        AS filles
             FROM eleves"
        );
        $row = $stmt->fetch();
        return [
            'total'    => (int)$row->total,
            'actifs'   => (int)$row->actifs,
            'inactifs' => (int)$row->inactifs,
            'garcons'  => (int)$row->garcons,
            'filles'   => (int)$row->filles,
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Inventaire\Repositories;

use Core\Database;

class MaintenanceRepository
{
    private \PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    public function all(int $etablissementId, array $filters = []): array
    {
        $where  = ['m.etablissement_id = :etab', 'm.deleted_at IS NULL'];
        $params = [':etab' => $etablissementId];
        if (!empty($filters['statut'])) {
            $where[]          = 'm.statut = :statut';
            $params[':statut'] = $filters['statut'];
        }
        if (!empty($filters['article_id'])) {
            $where[]         = 'm.article_id = :art';
            $params[':art']  = (int)$filters['article_id'];
        }
        $stmt = $this->pdo->prepare(
            'SELECT m.*, a.designation, a.reference
               FROM inv_maintenances m
               JOIN inv_articles a ON a.id = m.article_id
              WHERE ' . implode(' AND ', $where) . '
           ORDER BY m.date_planifiee DESC'
        );
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT m.*, a.designation, a.reference
               FROM inv_maintenances m
               JOIN inv_articles a ON a.id = m.article_id
              WHERE m.id = :id AND m.deleted_at IS NULL'
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO inv_maintenances
             (article_id, type, statut, date_planifiee, prestataire, cout, description, created_by, etablissement_id)
             VALUES (:article_id, :type, :statut, :date_planifiee, :prestataire, :cout, :description, :created_by, :etablissement_id)'
        );
        $stmt->execute($data);
        return (int)$this->pdo->lastInsertId();
    }

    public function updateStatut(int $id, string $statut, array $extra = []): void
    {
        $sets   = ['statut = :statut'];
        $params = [':statut' => $statut, ':id' => $id];
        foreach ($extra as $col => $val) {
            $sets[]          = "$col = :$col";
            $params[":$col"] = $val;
        }
        $this->pdo->prepare(
            'UPDATE inv_maintenances SET ' . implode(', ', $sets) . ' WHERE id = :id'
        )->execute($params);
    }

    public function softDelete(int $id): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE inv_maintenances SET deleted_at=NOW() WHERE id=:id AND statut="planifiee"'
        );
        $stmt->execute([':id' => $id]);
    }

    public function duesThisMonth(int $etablissementId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT m.*, a.designation
               FROM inv_maintenances m
               JOIN inv_articles a ON a.id = m.article_id
              WHERE m.etablissement_id = :etab
                AND m.statut = "planifiee"
                AND YEAR(m.date_planifiee)  = YEAR(CURDATE())
                AND MONTH(m.date_planifiee) = MONTH(CURDATE())'
        );
        $stmt->execute([':etab' => $etablissementId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}

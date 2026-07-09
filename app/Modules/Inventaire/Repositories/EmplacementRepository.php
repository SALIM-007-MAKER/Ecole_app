<?php

declare(strict_types=1);

namespace App\Modules\Inventaire\Repositories;

use Core\Database;

class EmplacementRepository
{
    private \PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    public function all(int $etablissementId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT e.*, p.nom AS parent_nom
               FROM inv_emplacements e
          LEFT JOIN inv_emplacements p ON p.id = e.parent_id
              WHERE e.etablissement_id = :etab
           ORDER BY e.nom ASC'
        );
        $stmt->execute([':etab' => $etablissementId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM inv_emplacements WHERE id = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO inv_emplacements (nom, code, description, type, parent_id, etablissement_id)
             VALUES (:nom, :code, :description, :type, :parent_id, :etablissement_id)'
        );
        $stmt->execute($data);
        return (int)$this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE inv_emplacements SET nom=:nom, code=:code, description=:description,
             type=:type, parent_id=:parent_id WHERE id=:id'
        );
        $data[':id'] = $id;
        $stmt->execute($data);
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM inv_emplacements WHERE id=:id');
        $stmt->execute([':id' => $id]);
    }

    public function hasStock(int $id): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM inv_stocks WHERE emplacement_id=:id AND quantite_disponible > 0'
        );
        $stmt->execute([':id' => $id]);
        return (int)$stmt->fetchColumn() > 0;
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Inventaire\Repositories;

use Core\Database;

class CategorieRepository
{
    private \PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    public function all(int $etablissementId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT c.*, p.nom AS parent_nom,
                    (SELECT COUNT(*) FROM inv_articles a WHERE a.categorie_id = c.id AND a.deleted_at IS NULL) AS nb_articles
               FROM inv_categories c
          LEFT JOIN inv_categories p ON p.id = c.parent_id
              WHERE c.etablissement_id = :etab AND c.deleted_at IS NULL
           ORDER BY c.nom ASC'
        );
        $stmt->execute([':etab' => $etablissementId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM inv_categories WHERE id = :id AND deleted_at IS NULL'
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO inv_categories (nom, description, parent_id, code, couleur, etablissement_id)
             VALUES (:nom, :description, :parent_id, :code, :couleur, :etablissement_id)'
        );
        $stmt->execute($data);
        return (int)$this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE inv_categories SET nom=:nom, description=:description, parent_id=:parent_id,
             code=:code, couleur=:couleur WHERE id=:id'
        );
        $data[':id'] = $id;
        $stmt->execute($data);
    }

    public function softDelete(int $id): void
    {
        $stmt = $this->pdo->prepare('UPDATE inv_categories SET deleted_at=NOW() WHERE id=:id');
        $stmt->execute([':id' => $id]);
    }

    public function hasArticles(int $id): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM inv_articles WHERE categorie_id = :id AND deleted_at IS NULL'
        );
        $stmt->execute([':id' => $id]);
        return (int)$stmt->fetchColumn() > 0;
    }
}

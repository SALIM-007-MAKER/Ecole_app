<?php

declare(strict_types=1);

namespace App\Modules\Bibliotheque\Repositories;

use Core\Database;
use PDO;

class CategorieRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    public function insert(array $data): int
    {
        $st = $this->pdo->prepare(
            "INSERT INTO biblio_categories (nom, description, parent_id, ordre, couleur, etablissement_id)
             VALUES (:nom, :desc, :parent, :ordre, :couleur, :etab)"
        );
        $st->execute([
            ':nom'    => $data['nom'],
            ':desc'   => $data['description'] ?? null,
            ':parent' => $data['parent_id'] ?? null,
            ':ordre'  => $data['ordre'] ?? 0,
            ':couleur'=> $data['couleur'] ?? null,
            ':etab'   => $data['etablissement_id'] ?? 1,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $this->pdo->prepare(
            "UPDATE biblio_categories SET nom=:nom, description=:desc, parent_id=:parent, ordre=:ordre, couleur=:couleur WHERE id=:id"
        )->execute([
            ':nom'    => $data['nom'],
            ':desc'   => $data['description'] ?? null,
            ':parent' => $data['parent_id'] ?? null,
            ':ordre'  => $data['ordre'] ?? 0,
            ':couleur'=> $data['couleur'] ?? null,
            ':id'     => $id,
        ]);
    }

    public function softDelete(int $id): void
    {
        $this->pdo->prepare("UPDATE biblio_categories SET deleted_at=NOW() WHERE id=:id")->execute([':id' => $id]);
    }

    public function findById(int $id): ?array
    {
        $st = $this->pdo->prepare("SELECT * FROM biblio_categories WHERE id=:id AND deleted_at IS NULL");
        $st->execute([':id' => $id]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function findAll(int $etablissementId): array
    {
        $st = $this->pdo->prepare(
            "SELECT * FROM biblio_categories WHERE etablissement_id=:etab AND deleted_at IS NULL ORDER BY ordre, nom"
        );
        $st->execute([':etab' => $etablissementId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findTree(int $etablissementId): array
    {
        $all  = $this->findAll($etablissementId);
        $tree = [];
        $map  = [];
        foreach ($all as &$item) {
            $item['children'] = [];
            $map[$item['id']] = &$item;
        }
        foreach ($all as &$item) {
            if ($item['parent_id'] === null) {
                $tree[] = &$item;
            } elseif (isset($map[$item['parent_id']])) {
                $map[$item['parent_id']]['children'][] = &$item;
            }
        }
        return $tree;
    }
}

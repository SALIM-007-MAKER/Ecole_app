<?php

declare(strict_types=1);

namespace App\Modules\Documents\Repositories;

use Core\Database;
use PDO;

class FolderRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    public function findById(int $id): ?array
    {
        $st = $this->pdo->prepare(
            "SELECT f.*, p.nom AS parent_nom
             FROM doc_folders f
             LEFT JOIN doc_folders p ON p.id = f.parent_id
             WHERE f.id = :id AND f.deleted_at IS NULL"
        );
        $st->execute([':id' => $id]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function findByModule(?string $moduleSource = null, int $etablissementId = 1): array
    {
        if ($moduleSource !== null && $moduleSource !== '') {
            $st = $this->pdo->prepare(
                "SELECT * FROM doc_folders
                 WHERE module_source = :mod AND etablissement_id = :etab AND deleted_at IS NULL
                 ORDER BY parent_id IS NULL DESC, ordre ASC, nom ASC"
            );
            $st->execute([':mod' => $moduleSource, ':etab' => $etablissementId]);
        } else {
            $st = $this->pdo->prepare(
                "SELECT * FROM doc_folders
                 WHERE etablissement_id = :etab AND deleted_at IS NULL
                 ORDER BY module_source ASC, parent_id IS NULL DESC, ordre ASC, nom ASC"
            );
            $st->execute([':etab' => $etablissementId]);
        }
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findChildren(int $parentId): array
    {
        $st = $this->pdo->prepare(
            "SELECT * FROM doc_folders
             WHERE parent_id = :pid AND deleted_at IS NULL
             ORDER BY ordre ASC, nom ASC"
        );
        $st->execute([':pid' => $parentId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findRoots(string $moduleSource, int $etablissementId = 1): array
    {
        $st = $this->pdo->prepare(
            "SELECT * FROM doc_folders
             WHERE module_source = :mod AND etablissement_id = :etab
               AND parent_id IS NULL AND deleted_at IS NULL
             ORDER BY ordre ASC, nom ASC"
        );
        $st->execute([':mod' => $moduleSource, ':etab' => $etablissementId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function hasChildren(int $folderId): bool
    {
        $st = $this->pdo->prepare(
            "SELECT COUNT(*) FROM doc_folders WHERE parent_id = :pid AND deleted_at IS NULL"
        );
        $st->execute([':pid' => $folderId]);
        return (int)$st->fetchColumn() > 0;
    }

    public function hasDocuments(int $folderId): bool
    {
        $st = $this->pdo->prepare(
            "SELECT COUNT(*) FROM doc_documents WHERE folder_id = :fid AND deleted_at IS NULL"
        );
        $st->execute([':fid' => $folderId]);
        return (int)$st->fetchColumn() > 0;
    }

    public function insert(array $data): int
    {
        $st = $this->pdo->prepare(
            "INSERT INTO doc_folders
                (parent_id, nom, description, module_source, etablissement_id, icone, couleur, ordre, created_by)
             VALUES (:parent_id, :nom, :desc, :mod, :etab, :icone, :couleur, :ordre, :by)"
        );
        $st->execute([
            ':parent_id' => $data['parent_id'] ?? null,
            ':nom'       => $data['nom'],
            ':desc'      => $data['description'] ?? null,
            ':mod'       => $data['module_source'],
            ':etab'      => $data['etablissement_id'] ?? 1,
            ':icone'     => $data['icone'] ?? 'folder',
            ':couleur'   => $data['couleur'] ?? 'slate',
            ':ordre'     => $data['ordre'] ?? 0,
            ':by'        => $data['created_by'] ?? 0,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $allowed = ['nom','description','parent_id','icone','couleur','ordre'];
        $fields  = [];
        $params  = [':id' => $id];
        foreach ($allowed as $col) {
            if (array_key_exists($col, $data)) {
                $fields[]      = "$col = :$col";
                $params[":$col"] = $data[$col];
            }
        }
        if (empty($fields)) return;
        $this->pdo->prepare("UPDATE doc_folders SET " . implode(', ', $fields) . " WHERE id = :id")
                  ->execute($params);
    }

    public function softDelete(int $id): void
    {
        $this->pdo->prepare("UPDATE doc_folders SET deleted_at = NOW() WHERE id = :id")
                  ->execute([':id' => $id]);
    }

    public function breadcrumb(int $folderId): array
    {
        $path   = [];
        $current = $this->findById($folderId);
        while ($current) {
            array_unshift($path, $current);
            $current = $current['parent_id'] ? $this->findById((int)$current['parent_id']) : null;
        }
        return $path;
    }
}

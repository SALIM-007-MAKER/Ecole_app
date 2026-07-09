<?php

declare(strict_types=1);

namespace App\Modules\Documents\Repositories;

use Core\Database;
use PDO;

class TagRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    public function findAll(?string $moduleSource = null, int $etablissementId = 1): array
    {
        $modClause = $moduleSource !== null ? ' AND (module_source = :mod OR module_source IS NULL)' : '';
        $params    = [':etab' => $etablissementId];
        if ($moduleSource !== null) $params[':mod'] = $moduleSource;

        $st = $this->pdo->prepare(
            "SELECT t.*, COUNT(dt.document_id) AS usage_count
             FROM doc_tags t
             LEFT JOIN doc_document_tags dt ON dt.tag_id = t.id
             WHERE t.etablissement_id = :etab $modClause
             GROUP BY t.id ORDER BY usage_count DESC, t.nom ASC"
        );
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findByDocument(int $documentId): array
    {
        $st = $this->pdo->prepare(
            "SELECT t.* FROM doc_tags t
             JOIN doc_document_tags dt ON dt.tag_id = t.id
             WHERE dt.document_id = :id ORDER BY t.nom ASC"
        );
        $st->execute([':id' => $documentId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById(int $id): ?array
    {
        $st = $this->pdo->prepare("SELECT * FROM doc_tags WHERE id = :id");
        $st->execute([':id' => $id]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function findByNom(string $nom, int $etablissementId = 1): ?array
    {
        $st = $this->pdo->prepare(
            "SELECT * FROM doc_tags WHERE nom = :nom AND etablissement_id = :etab"
        );
        $st->execute([':nom' => $nom, ':etab' => $etablissementId]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function insert(array $data): int
    {
        $st = $this->pdo->prepare(
            "INSERT INTO doc_tags (nom, couleur, module_source, etablissement_id, created_by)
             VALUES (:nom, :couleur, :mod, :etab, :by)"
        );
        $st->execute([
            ':nom'    => $data['nom'],
            ':couleur'=> $data['couleur'] ?? 'slate',
            ':mod'    => $data['module_source'] ?? null,
            ':etab'   => $data['etablissement_id'] ?? 1,
            ':by'     => $data['created_by'] ?? 0,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function attachTags(int $documentId, array $tagIds, int $userId): void
    {
        $st = $this->pdo->prepare(
            "INSERT IGNORE INTO doc_document_tags (document_id, tag_id, created_by) VALUES (:doc, :tag, :by)"
        );
        foreach ($tagIds as $tagId) {
            $st->execute([':doc' => $documentId, ':tag' => $tagId, ':by' => $userId]);
        }
    }

    public function detachTag(int $documentId, int $tagId): void
    {
        $this->pdo->prepare(
            "DELETE FROM doc_document_tags WHERE document_id = :doc AND tag_id = :tag"
        )->execute([':doc' => $documentId, ':tag' => $tagId]);
    }

    public function syncTags(int $documentId, array $tagIds, int $userId): void
    {
        $this->pdo->prepare("DELETE FROM doc_document_tags WHERE document_id = :doc")
                  ->execute([':doc' => $documentId]);
        if (!empty($tagIds)) {
            $this->attachTags($documentId, $tagIds, $userId);
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Documents\Repositories;

use Core\Database;
use PDO;

class TrashRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    public function insert(array $data): void
    {
        $purge = date('Y-m-d H:i:s', strtotime('+30 days'));
        $this->pdo->prepare(
            "INSERT INTO doc_corbeille (document_id, raison, created_by, purge_avant)
             VALUES (:doc, :raison, :by, :purge)
             ON DUPLICATE KEY UPDATE raison = VALUES(raison), created_by = VALUES(created_by),
             created_at = NOW(), purge_avant = VALUES(purge_avant)"
        )->execute([
            ':doc'   => $data['document_id'],
            ':raison'=> $data['raison'] ?? null,
            ':by'    => $data['created_by'] ?? 0,
            ':purge' => $data['purge_avant'] ?? $purge,
        ]);
    }

    public function remove(int $documentId): void
    {
        $this->pdo->prepare("DELETE FROM doc_corbeille WHERE document_id = :id")
                  ->execute([':id' => $documentId]);
    }

    public function findDuePurge(): array
    {
        $st = $this->pdo->prepare(
            "SELECT cb.*, d.chemin_stockage, d.module_source
             FROM doc_corbeille cb
             JOIN doc_documents d ON d.id = cb.document_id
             WHERE cb.purge_avant IS NOT NULL AND cb.purge_avant <= NOW()"
        );
        $st->execute();
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }
}

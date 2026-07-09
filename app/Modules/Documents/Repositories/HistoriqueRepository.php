<?php

declare(strict_types=1);

namespace App\Modules\Documents\Repositories;

use Core\Database;
use PDO;

class HistoriqueRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    public function insert(array $data): void
    {
        $st = $this->pdo->prepare(
            "INSERT INTO doc_historique
                (document_id, action, details, ip, user_agent, created_by, created_by_nom)
             VALUES (:doc_id, :action, :details, :ip, :ua, :by, :by_nom)"
        );
        $st->execute([
            ':doc_id'  => $data['document_id'],
            ':action'  => $data['action'],
            ':details' => isset($data['details']) ? json_encode($data['details']) : null,
            ':ip'      => $data['ip'] ?? ($_SERVER['REMOTE_ADDR'] ?? null),
            ':ua'      => $data['user_agent'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? null),
            ':by'      => $data['created_by'] ?? 0,
            ':by_nom'  => $data['created_by_nom'] ?? null,
        ]);
    }

    public function findByDocument(int $documentId): array
    {
        $st = $this->pdo->prepare(
            "SELECT * FROM doc_historique WHERE document_id = :id ORDER BY created_at DESC"
        );
        $st->execute([':id' => $documentId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findRecentsByModule(string $moduleSource, int $limit = 50): array
    {
        $st = $this->pdo->prepare(
            "SELECT h.*, d.titre, d.module_source
             FROM doc_historique h
             JOIN doc_documents d ON d.id = h.document_id
             WHERE d.module_source = :mod
             ORDER BY h.created_at DESC
             LIMIT :limit"
        );
        $st->execute([':mod' => $moduleSource, ':limit' => $limit]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }
}

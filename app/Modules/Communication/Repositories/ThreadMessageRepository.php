<?php

declare(strict_types=1);

namespace App\Modules\Communication\Repositories;

use Core\Database;
use PDO;

class ThreadMessageRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    public function insert(array $data): int
    {
        $st = $this->pdo->prepare(
            "INSERT INTO com_thread_messages (thread_id, user_id, corps, type, metadata)
             VALUES (:tid, :uid, :corps, :type, :meta)"
        );
        $st->execute([
            ':tid'   => $data['thread_id'],
            ':uid'   => $data['user_id'],
            ':corps' => $data['corps'],
            ':type'  => $data['type'] ?? 'texte',
            ':meta'  => isset($data['metadata']) ? json_encode($data['metadata']) : null,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function findByThread(int $threadId, int $page = 1, int $perPage = 30): array
    {
        $offset = ($page - 1) * $perPage;
        $st = $this->pdo->prepare(
            "SELECT m.*, u.prenom, u.nom
             FROM com_thread_messages m
             LEFT JOIN users u ON u.id = m.user_id
             WHERE m.thread_id = :tid AND m.deleted_at IS NULL
             ORDER BY m.created_at ASC
             LIMIT :limit OFFSET :offset"
        );
        $st->bindValue(':tid',    $threadId, PDO::PARAM_INT);
        $st->bindValue(':limit',  $perPage,  PDO::PARAM_INT);
        $st->bindValue(':offset', $offset,   PDO::PARAM_INT);
        $st->execute();
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function softDelete(int $id, int $userId): bool
    {
        $st = $this->pdo->prepare(
            "UPDATE com_thread_messages SET deleted_at = NOW()
             WHERE id = :id AND user_id = :uid"
        );
        return $st->execute([':id' => $id, ':uid' => $userId]);
    }
}

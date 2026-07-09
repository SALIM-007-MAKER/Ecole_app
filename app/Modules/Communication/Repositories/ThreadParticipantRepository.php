<?php

declare(strict_types=1);

namespace App\Modules\Communication\Repositories;

use Core\Database;
use PDO;

class ThreadParticipantRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    public function insert(int $threadId, int $userId, string $role = 'membre'): void
    {
        $st = $this->pdo->prepare(
            "INSERT IGNORE INTO com_thread_participants (thread_id, user_id, role)
             VALUES (:tid, :uid, :role)"
        );
        $st->execute([':tid' => $threadId, ':uid' => $userId, ':role' => $role]);
    }

    public function findByThread(int $threadId): array
    {
        $st = $this->pdo->prepare(
            "SELECT p.*, u.prenom, u.nom, u.email, u.role AS user_role
             FROM com_thread_participants p
             LEFT JOIN users u ON u.id = p.user_id
             WHERE p.thread_id = :tid"
        );
        $st->execute([':tid' => $threadId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function isMember(int $threadId, int $userId): bool
    {
        $st = $this->pdo->prepare(
            "SELECT id FROM com_thread_participants WHERE thread_id = :tid AND user_id = :uid"
        );
        $st->execute([':tid' => $threadId, ':uid' => $userId]);
        return $st->fetch() !== false;
    }

    public function updateLuAt(int $threadId, int $userId): void
    {
        $st = $this->pdo->prepare(
            "UPDATE com_thread_participants SET lu_at = NOW()
             WHERE thread_id = :tid AND user_id = :uid"
        );
        $st->execute([':tid' => $threadId, ':uid' => $userId]);
    }

    public function findUserIds(int $threadId): array
    {
        $st = $this->pdo->prepare(
            "SELECT user_id FROM com_thread_participants WHERE thread_id = :tid"
        );
        $st->execute([':tid' => $threadId]);
        return array_column($st->fetchAll(PDO::FETCH_ASSOC), 'user_id');
    }

    public function remove(int $threadId, int $userId): bool
    {
        $st = $this->pdo->prepare(
            "DELETE FROM com_thread_participants WHERE thread_id = :tid AND user_id = :uid"
        );
        return $st->execute([':tid' => $threadId, ':uid' => $userId]);
    }
}

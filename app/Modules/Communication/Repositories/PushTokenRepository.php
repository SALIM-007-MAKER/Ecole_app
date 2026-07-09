<?php

declare(strict_types=1);

namespace App\Modules\Communication\Repositories;

use Core\Database;
use PDO;

class PushTokenRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    public function upsert(int $userId, string $token, string $plateforme = 'web'): void
    {
        $st = $this->pdo->prepare(
            "INSERT INTO com_push_tokens (user_id, token, plateforme, actif)
             VALUES (:uid, :token, :plat, 1)
             ON DUPLICATE KEY UPDATE user_id = :uid2, plateforme = :plat2, actif = 1, last_used_at = NOW()"
        );
        $st->execute([
            ':uid'   => $userId, ':uid2' => $userId,
            ':token' => $token,
            ':plat'  => $plateforme, ':plat2' => $plateforme,
        ]);
    }

    public function findByUser(int $userId): array
    {
        $st = $this->pdo->prepare(
            "SELECT * FROM com_push_tokens WHERE user_id = :uid AND actif = 1"
        );
        $st->execute([':uid' => $userId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function revoke(string $token): void
    {
        $st = $this->pdo->prepare(
            "UPDATE com_push_tokens SET actif = 0 WHERE token = :token"
        );
        $st->execute([':token' => $token]);
    }

    public function updateLastUsed(string $token): void
    {
        $st = $this->pdo->prepare(
            "UPDATE com_push_tokens SET last_used_at = NOW() WHERE token = :token"
        );
        $st->execute([':token' => $token]);
    }
}

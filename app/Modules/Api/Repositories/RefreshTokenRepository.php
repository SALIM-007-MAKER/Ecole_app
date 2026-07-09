<?php
declare(strict_types=1);

namespace App\Modules\Api\Repositories;

use Core\Database;

class RefreshTokenRepository
{
    private \PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    public function create(int $userId, int $etabId, string $hash, string $expiresAt, string $ua, string $ip): void
    {
        $this->pdo->prepare(
            'INSERT INTO api_refresh_tokens (user_id, etablissement_id, token_hash, expires_at, user_agent, ip_address)
             VALUES (:uid, :etab, :hash, :exp, :ua, :ip)'
        )->execute([':uid' => $userId, ':etab' => $etabId, ':hash' => $hash, ':exp' => $expiresAt, ':ua' => $ua, ':ip' => $ip]);
    }

    public function findValid(string $hash): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT user_id, etablissement_id, token_hash FROM api_refresh_tokens
             WHERE token_hash = :hash AND revoked_at IS NULL AND expires_at > NOW() LIMIT 1'
        );
        $stmt->execute([':hash' => $hash]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    public function revoke(string $hash): void
    {
        $this->pdo->prepare('UPDATE api_refresh_tokens SET revoked_at = NOW() WHERE token_hash = :hash')
                  ->execute([':hash' => $hash]);
    }

    public function revokeAllForUser(int $userId): void
    {
        $this->pdo->prepare('UPDATE api_refresh_tokens SET revoked_at = NOW() WHERE user_id = :uid AND revoked_at IS NULL')
                  ->execute([':uid' => $userId]);
    }

    public function purgeExpired(): void
    {
        $this->pdo->exec('DELETE FROM api_refresh_tokens WHERE expires_at <= NOW()');
    }
}

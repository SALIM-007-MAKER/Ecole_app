<?php
declare(strict_types=1);

namespace App\Modules\Portals\Repositories;

use Core\Database;

class ApiTokenRepository
{
    private \PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    public function create(int $userId, string $portal, int $etablissementId, int $ttlSeconds = 3600): string
    {
        $token     = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', time() + $ttlSeconds);
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO portal_api_tokens (user_id, token, portal, etablissement_id, expires_at)
                 VALUES (:uid, :token, :portal, :etab, :exp)'
            );
            $stmt->execute([
                ':uid'    => $userId,
                ':token'  => $token,
                ':portal' => $portal,
                ':etab'   => $etablissementId,
                ':exp'    => $expiresAt,
            ]);
        } catch (\Throwable $e) {
            error_log('[ApiTokenRepository] create: ' . $e->getMessage());
        }
        return $token;
    }

    public function findValid(string $token): ?array
    {
        try {
            $stmt = $this->pdo->prepare(
                'SELECT user_id, portal, etablissement_id, expires_at
                 FROM portal_api_tokens
                 WHERE token = :token AND expires_at > NOW()
                 LIMIT 1'
            );
            $stmt->execute([':token' => $token]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (\Throwable) {
            return null;
        }
    }

    public function revoke(string $token): void
    {
        try {
            $stmt = $this->pdo->prepare('DELETE FROM portal_api_tokens WHERE token = :token');
            $stmt->execute([':token' => $token]);
        } catch (\Throwable) {}
    }

    public function revokeAllForUser(int $userId, ?string $portal = null): void
    {
        try {
            $sql    = 'DELETE FROM portal_api_tokens WHERE user_id = :uid';
            $params = [':uid' => $userId];
            if ($portal !== null) {
                $sql          .= ' AND portal = :portal';
                $params[':portal'] = $portal;
            }
            $this->pdo->prepare($sql)->execute($params);
        } catch (\Throwable) {}
    }

    public function purgeExpired(): void
    {
        try {
            $this->pdo->exec('DELETE FROM portal_api_tokens WHERE expires_at <= NOW()');
        } catch (\Throwable) {}
    }
}

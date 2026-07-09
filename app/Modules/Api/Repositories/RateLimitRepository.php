<?php
declare(strict_types=1);

namespace App\Modules\Api\Repositories;

use Core\Database;

class RateLimitRepository
{
    private \PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    /**
     * Consomme un token du bucket. Retourne [remaining, reset_at].
     * Implémentation Sliding Window via INSERT...ON DUPLICATE KEY.
     */
    public function consume(string $bucketKey, int $limit, int $windowSeconds): array
    {
        try {
            $now    = microtime(true);
            $expiry = date('Y-m-d H:i:s', (int)$now + $windowSeconds);

            // Upsert: si le bucket n'existe pas ou est expiré, créer avec limit-1 tokens
            $this->pdo->prepare(
                'INSERT INTO api_rate_limit_buckets (bucket_key, tokens, last_refill, expires_at)
                 VALUES (:key, :init, NOW(6), :exp)
                 ON DUPLICATE KEY UPDATE
                   tokens = GREATEST(
                     IF(expires_at <= NOW(), :init, tokens - 1),
                     -1
                   ),
                   expires_at = IF(expires_at <= NOW(), :exp, expires_at),
                   last_refill = IF(expires_at <= NOW(), NOW(6), last_refill)'
            )->execute([':key' => $bucketKey, ':init' => $limit - 1, ':exp' => $expiry]);

            $stmt = $this->pdo->prepare('SELECT tokens, expires_at FROM api_rate_limit_buckets WHERE bucket_key = :key');
            $stmt->execute([':key' => $bucketKey]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);

            $tokens    = (int)($row['tokens'] ?? 0);
            $remaining = max(0, $tokens);
            return ['remaining' => $remaining, 'reset_at' => $row['expires_at'] ?? $expiry, 'allowed' => $tokens >= 0];
        } catch (\Throwable) {
            // En cas d'erreur DB, on laisse passer (fail open)
            return ['remaining' => $limit, 'reset_at' => date('Y-m-d H:i:s', time() + $windowSeconds), 'allowed' => true];
        }
    }

    public function purgeExpired(): void
    {
        try {
            $this->pdo->exec('DELETE FROM api_rate_limit_buckets WHERE expires_at <= NOW()');
        } catch (\Throwable) {}
    }
}

<?php
declare(strict_types=1);

namespace App\Modules\Portals\Repositories;

use Core\Database;

class WidgetCacheRepository
{
    private \PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    public function get(string $widgetId, string $portal, int $etablissementId, ?int $userId = null): ?array
    {
        try {
            $sql = 'SELECT data, expires_at FROM portal_widget_cache
                    WHERE widget_id = :wid AND etablissement_id = :etab
                    AND expires_at > NOW()';
            $params = [':wid' => $widgetId, ':etab' => $etablissementId];

            if ($userId !== null) {
                $sql .= ' AND user_id = :uid';
                $params[':uid'] = $userId;
            } else {
                $sql .= ' AND user_id IS NULL';
            }

            $sql .= ' LIMIT 1';
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!$row) {
                return null;
            }
            return [
                'data'       => json_decode($row['data'], true) ?? [],
                'expires_at' => $row['expires_at'],
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    public function set(
        string $widgetId,
        string $portal,
        int    $etablissementId,
        array  $data,
        int    $ttlSeconds,
        ?int   $userId = null
    ): void {
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO portal_widget_cache
                 (portal, widget_id, user_id, etablissement_id, data, expires_at)
                 VALUES (:portal, :wid, :uid, :etab, :data, DATE_ADD(NOW(), INTERVAL :ttl SECOND))
                 ON DUPLICATE KEY UPDATE
                   data = VALUES(data),
                   expires_at = DATE_ADD(NOW(), INTERVAL :ttl2 SECOND)'
            );
            $stmt->execute([
                ':portal' => $portal,
                ':wid'    => $widgetId,
                ':uid'    => $userId,
                ':etab'   => $etablissementId,
                ':data'   => json_encode($data),
                ':ttl'    => $ttlSeconds,
                ':ttl2'   => $ttlSeconds,
            ]);
        } catch (\Throwable $e) {
            error_log('[WidgetCacheRepository] set: ' . $e->getMessage());
        }
    }

    public function invalidate(string $portal, int $etablissementId): void
    {
        try {
            $stmt = $this->pdo->prepare(
                'DELETE FROM portal_widget_cache
                 WHERE portal = :portal AND etablissement_id = :etab'
            );
            $stmt->execute([':portal' => $portal, ':etab' => $etablissementId]);
        } catch (\Throwable) {}
    }

    public function purgeExpired(): void
    {
        try {
            $this->pdo->exec('DELETE FROM portal_widget_cache WHERE expires_at <= NOW()');
        } catch (\Throwable) {}
    }
}

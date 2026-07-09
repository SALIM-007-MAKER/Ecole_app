<?php
declare(strict_types=1);

namespace App\Modules\Portals\Repositories;

use Core\Database;

class PreferencesRepository
{
    private \PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    public function find(int $userId, string $portal, int $etablissementId): ?array
    {
        try {
            $stmt = $this->pdo->prepare(
                'SELECT * FROM portal_preferences
                 WHERE user_id = :uid AND portal = :portal AND etablissement_id = :etab
                 AND deleted_at IS NULL LIMIT 1'
            );
            $stmt->execute([':uid' => $userId, ':portal' => $portal, ':etab' => $etablissementId]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (\Throwable) {
            return null;
        }
    }

    public function upsert(int $userId, string $portal, int $etablissementId, array $fields): void
    {
        if (empty($fields)) {
            return;
        }
        try {
            $setClauses = implode(', ', array_map(fn($k) => "`{$k}` = :{$k}", array_keys($fields)));
            $colList    = implode(', ', array_map(fn($k) => "`{$k}`", array_keys($fields)));
            $valList    = implode(', ', array_map(fn($k) => ":{$k}", array_keys($fields)));

            $stmt = $this->pdo->prepare(
                "INSERT INTO portal_preferences (user_id, portal, etablissement_id, {$colList}, updated_at)
                 VALUES (:user_id, :portal, :etab, {$valList}, NOW())
                 ON DUPLICATE KEY UPDATE {$setClauses}, updated_at = NOW()"
            );
            $params = array_merge(
                [':user_id' => $userId, ':portal' => $portal, ':etab' => $etablissementId],
                array_combine(array_map(fn($k) => ":{$k}", array_keys($fields)), array_values($fields))
            );
            $stmt->execute($params);
        } catch (\Throwable $e) {
            error_log('[PreferencesRepository] upsert: ' . $e->getMessage());
        }
    }

    public function upsertField(int $userId, string $portal, int $etablissementId, string $field, mixed $value): void
    {
        $this->upsert($userId, $portal, $etablissementId, [$field => $value]);
    }

    public function delete(int $userId, string $portal, int $etablissementId): void
    {
        try {
            $stmt = $this->pdo->prepare(
                'UPDATE portal_preferences SET deleted_at = NOW()
                 WHERE user_id = :uid AND portal = :portal AND etablissement_id = :etab'
            );
            $stmt->execute([':uid' => $userId, ':portal' => $portal, ':etab' => $etablissementId]);
        } catch (\Throwable) {}
    }
}

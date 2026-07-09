<?php
declare(strict_types=1);

namespace App\Modules\Api\Repositories;

use Core\Database;

class ApiKeyRepository
{
    private \PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO api_keys
             (etablissement_id, name, key_prefix, key_hash, key_hint, permissions, rate_limit, allowed_ips, expires_at, created_by)
             VALUES (:etab, :name, :prefix, :hash, :hint, :perms, :rl, :ips, :exp, :by)'
        );
        $stmt->execute([
            ':etab'   => $data['etablissement_id'],
            ':name'   => $data['name'],
            ':prefix' => $data['key_prefix'],
            ':hash'   => $data['key_hash'],
            ':hint'   => $data['key_hint'],
            ':perms'  => $data['permissions'],
            ':rl'     => $data['rate_limit'],
            ':ips'    => $data['allowed_ips'],
            ':exp'    => $data['expires_at'],
            ':by'     => $data['created_by'],
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function findValid(string $hash): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM api_keys
             WHERE key_hash = :hash AND revoked_at IS NULL
               AND (expires_at IS NULL OR expires_at > NOW())
             LIMIT 1'
        );
        $stmt->execute([':hash' => $hash]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    public function touchLastUsed(int $id): void
    {
        $this->pdo->prepare('UPDATE api_keys SET last_used_at = NOW() WHERE id = :id')
                  ->execute([':id' => $id]);
    }

    public function revoke(int $id, int $etablissementId): void
    {
        $this->pdo->prepare('UPDATE api_keys SET revoked_at = NOW() WHERE id = :id AND etablissement_id = :etab')
                  ->execute([':id' => $id, ':etab' => $etablissementId]);
    }

    public function listForEtab(int $etablissementId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, name, key_prefix, key_hint, permissions, rate_limit, last_used_at,
                    expires_at, revoked_at, created_at
             FROM api_keys WHERE etablissement_id = :etab ORDER BY created_at DESC'
        );
        $stmt->execute([':etab' => $etablissementId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}

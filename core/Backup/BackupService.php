<?php

declare(strict_types=1);

namespace Core\Backup;

use Core\Database;
use Core\Logger;
use Core\Storage\StorageManager;
use PDO;

/**
 * Orchestration des sauvegardes/restaurations — Phase 14.11.
 * Ledger : `platform_backups` (blueprint §19.4), `platform_restores`.
 */
final class BackupService
{
    private const RETENTION_DAYS = [
        'global' => 30, 'manual' => 30, 'pre_migration' => 7, 'tenant' => 28, 'differential' => 14,
    ];

    public function __construct(
        private readonly PDO $pdo,
        private readonly DatabaseDumper $dumper,
        private readonly DatabaseRestorer $restorer,
        private readonly BackupEncryption $encryption,
    ) {
    }

    public static function make(): self
    {
        $pdo = Database::getInstance()->getConnection();
        return new self($pdo, new DatabaseDumper($pdo), new DatabaseRestorer(), BackupEncryption::make());
    }

    public function createGlobal(int $operatorUserId, string $type = 'manual', bool $encrypt = true): array
    {
        if (!in_array($type, ['global', 'manual', 'pre_migration'], true)) {
            throw new \InvalidArgumentException("Type de sauvegarde globale invalide : {$type}");
        }
        return $this->runBackup($type, null, $operatorUserId, $encrypt, fn() => $this->dumper->dumpGlobal());
    }

    public function createTenant(int $etablissementId, int $operatorUserId, bool $encrypt = true): array
    {
        return $this->runBackup('tenant', $etablissementId, $operatorUserId, $encrypt, fn() => $this->dumper->dumpTenant($etablissementId));
    }

    public function createDifferential(?int $etablissementId, \DateTimeImmutable $since, int $operatorUserId, bool $encrypt = true): array
    {
        return $this->runBackup('differential', $etablissementId, $operatorUserId, $encrypt, fn() => $this->dumper->dumpDifferential($since, $etablissementId));
    }

    private function runBackup(string $type, ?int $etablissementId, int $operatorUserId, bool $encrypt, \Closure $dumpFn): array
    {
        $startedAt = microtime(true);

        $insert = $this->pdo->prepare(
            "INSERT INTO platform_backups (etablissement_id, type, statut, storage_path, triggered_by, started_at, expires_at)
             VALUES (?, ?, 'running', '', ?, NOW(), DATE_ADD(CURDATE(), INTERVAL ? DAY))"
        );
        $insert->execute([$etablissementId, $type, $operatorUserId, self::RETENTION_DAYS[$type] ?? 30]);
        $backupId = (int)$this->pdo->lastInsertId();

        try {
            $dump = $dumpFn();
            $json = json_encode($dump, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);

            $payload = $encrypt ? $this->encryption->encrypt($json) : $json;
            $checksum = hash('sha256', $payload);

            $filename = $type . '_' . date('Ymd_His') . '_' . substr(bin2hex(random_bytes(4)), 0, 8) . '.bak';
            $path = ($etablissementId !== null ? (string)$etablissementId : 'platform') . '/backups/' . $filename;

            StorageManager::driver()->put($path, $payload);

            $durationMs = (int)round((microtime(true) - $startedAt) * 1000);

            $update = $this->pdo->prepare(
                "UPDATE platform_backups SET statut = 'success', storage_path = ?, size_bytes = ?, checksum = ?,
                    encrypted = ?, completed_at = NOW(), duration_ms = ? WHERE id = ?"
            );
            $update->execute([$path, strlen($payload), $checksum, $encrypt ? 1 : 0, $durationMs, $backupId]);

            Logger::security('PLATFORM_BACKUP_CREATED', "backup_id={$backupId} type={$type} etablissement_id=" . ($etablissementId ?? 'NULL') . " taille=" . strlen($payload) . " par operator_user_id={$operatorUserId}");

            return [
                'id' => $backupId, 'type' => $type, 'etablissement_id' => $etablissementId,
                'size_bytes' => strlen($payload), 'checksum' => $checksum, 'duration_ms' => $durationMs,
                'table_count' => $dump['meta']['table_count'] ?? 0,
            ];
        } catch (\Throwable $e) {
            $update = $this->pdo->prepare(
                "UPDATE platform_backups SET statut = 'failed', completed_at = NOW(), error_message = ? WHERE id = ?"
            );
            $update->execute([$e->getMessage(), $backupId]);
            Logger::security('PLATFORM_BACKUP_FAILED', "backup_id={$backupId} type={$type} erreur=" . $e->getMessage());
            throw $e;
        }
    }

    public function verifyIntegrity(int $backupId): bool
    {
        $backup = $this->findBackup($backupId);
        if ($backup === null || $backup['statut'] !== 'success') {
            return false;
        }
        try {
            $payload = StorageManager::driver()->get($backup['storage_path']);
        } catch (\Throwable) {
            return false;
        }
        return hash_equals($backup['checksum'] ?? '', hash('sha256', $payload));
    }

    /** Lit, déchiffre si nécessaire, et décode une sauvegarde. */
    public function loadDump(int $backupId): array
    {
        $backup = $this->findBackup($backupId);
        if ($backup === null) {
            throw new \InvalidArgumentException("Sauvegarde introuvable : {$backupId}");
        }
        $payload = StorageManager::driver()->get($backup['storage_path']);

        if (!hash_equals($backup['checksum'] ?? '', hash('sha256', $payload))) {
            throw new \RuntimeException('Intégrité compromise : le checksum de la sauvegarde ne correspond pas au contenu stocké.');
        }

        $json = $backup['encrypted'] ? $this->encryption->decrypt($payload) : $payload;
        return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * Restauration tenant EN DIRECT (upsert, jamais destructif pour les
     * autres tenants) — exige une confirmation textuelle = slug exact du
     * tenant, filet de sécurité contre un clic accidentel sur une action
     * qui modifie des données réelles.
     */
    public function restoreTenantLive(int $backupId, int $etablissementId, int $operatorUserId, string $confirmation): array
    {
        $startedAt = microtime(true);

        $etabStmt = $this->pdo->prepare("SELECT slug FROM etablissements WHERE id = ?");
        $etabStmt->execute([$etablissementId]);
        $slug = $etabStmt->fetchColumn();
        if ($slug === false) {
            throw new \InvalidArgumentException('Établissement introuvable.');
        }
        if (!hash_equals((string)$slug, $confirmation)) {
            throw new \InvalidArgumentException('Confirmation invalide — le slug saisi ne correspond pas à cet établissement.');
        }

        $restoreId = $this->logRestoreStart('tenant_live', $backupId, $etablissementId, $operatorUserId, "slug={$slug}");

        try {
            $dump = $this->loadDump($backupId);
            $result = $this->restorer->restoreTenantLive($dump, $etablissementId, $this->pdo);

            $this->logRestoreEnd($restoreId, 'success', $result['rows_restored'], $startedAt);
            Logger::security('PLATFORM_RESTORE_TENANT_SUCCESS', "backup_id={$backupId} etablissement_id={$etablissementId} rows={$result['rows_restored']} par operator_user_id={$operatorUserId}");

            return $result;
        } catch (\Throwable $e) {
            $this->logRestoreEnd($restoreId, 'failed', null, $startedAt, $e->getMessage());
            Logger::security('PLATFORM_RESTORE_TENANT_FAILED', "backup_id={$backupId} etablissement_id={$etablissementId} erreur=" . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Vérification d'intégrité par restauration réelle dans une base
     * fraîchement créée puis immédiatement supprimée — ne touche JAMAIS la
     * base de production. C'est la méthode "vérification d'intégrité avant
     * restauration" de cette phase, appliquée à une sauvegarde globale.
     */
    public function restoreGlobalVerify(int $backupId, int $operatorUserId): array
    {
        $startedAt = microtime(true);
        $restoreId = $this->logRestoreStart('global_verify', $backupId, null, $operatorUserId, null);

        $scratchDb = 'edunova_backup_verify_' . date('Ymd_His') . '_' . substr(bin2hex(random_bytes(3)), 0, 6);
        $rawPdo = $this->openRawConnection(null);

        try {
            $rawPdo->exec("CREATE DATABASE `{$scratchDb}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $scratchPdo = $this->openRawConnection($scratchDb);

            $dump = $this->loadDump($backupId);
            $result = $this->restorer->restoreGlobalToScratch($dump, $scratchPdo);

            $this->logRestoreEnd($restoreId, 'success', $result['rows_restored'], $startedAt, null, "db={$scratchDb} (supprimée après vérification)");
            Logger::security('PLATFORM_RESTORE_VERIFY_SUCCESS', "backup_id={$backupId} tables={$result['tables_created']} rows={$result['rows_restored']} par operator_user_id={$operatorUserId}");

            return $result + ['scratch_db' => $scratchDb];
        } catch (\Throwable $e) {
            $this->logRestoreEnd($restoreId, 'failed', null, $startedAt, $e->getMessage());
            Logger::security('PLATFORM_RESTORE_VERIFY_FAILED', "backup_id={$backupId} erreur=" . $e->getMessage());
            throw $e;
        } finally {
            $rawPdo->exec("DROP DATABASE IF EXISTS `{$scratchDb}`");
        }
    }

    /** @return list<array<string,mixed>> */
    public function history(?int $etablissementId = null, int $limit = 50): array
    {
        $where = $etablissementId !== null ? 'WHERE etablissement_id = ?' : '';
        $params = $etablissementId !== null ? [$etablissementId] : [];
        $stmt = $this->pdo->prepare(
            "SELECT * FROM platform_backups {$where} ORDER BY created_at DESC LIMIT " . max(1, min(500, $limit))
        );
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return list<array<string,mixed>> */
    public function restoreHistory(int $limit = 50): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT r.*, b.type AS backup_type FROM platform_restores r
             JOIN platform_backups b ON b.id = r.backup_id
             ORDER BY r.created_at DESC LIMIT " . max(1, min(500, $limit))
        );
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array{total:int, success:int, failed:int, avg_duration_ms:?float, total_size_bytes:int} */
    public function stats(): array
    {
        $row = $this->pdo->query(
            "SELECT COUNT(*) AS total,
                    SUM(statut = 'success') AS success,
                    SUM(statut = 'failed') AS failed,
                    AVG(CASE WHEN statut = 'success' THEN duration_ms END) AS avg_duration_ms,
                    SUM(CASE WHEN statut = 'success' THEN size_bytes ELSE 0 END) AS total_size_bytes
             FROM platform_backups"
        )->fetch(PDO::FETCH_ASSOC);

        return [
            'total'            => (int)($row['total'] ?? 0),
            'success'          => (int)($row['success'] ?? 0),
            'failed'           => (int)($row['failed'] ?? 0),
            'avg_duration_ms'  => $row['avg_duration_ms'] !== null ? (float)$row['avg_duration_ms'] : null,
            'total_size_bytes' => (int)($row['total_size_bytes'] ?? 0),
        ];
    }

    /** Supprime (stockage + ligne) les sauvegardes dont la rétention est dépassée. Callable manuellement (pas de planification automatique — voir rapport). */
    public function purgeExpired(): int
    {
        $stmt = $this->pdo->query("SELECT id, storage_path FROM platform_backups WHERE expires_at IS NOT NULL AND expires_at < CURDATE()");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $count = 0;
        foreach ($rows as $row) {
            try {
                if (StorageManager::driver()->exists($row['storage_path'])) {
                    StorageManager::driver()->delete($row['storage_path']);
                }
            } catch (\Throwable) {
                // fichier déjà absent — on purge quand même la ligne
            }
            $del = $this->pdo->prepare("DELETE FROM platform_backups WHERE id = ?");
            $del->execute([$row['id']]);
            $count++;
        }
        return $count;
    }

    private function findBackup(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM platform_backups WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function logRestoreStart(string $type, int $backupId, ?int $etablissementId, int $operatorUserId, ?string $targetInfo): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO platform_restores (backup_id, etablissement_id, type, statut, target_info, triggered_by, started_at)
             VALUES (?, ?, ?, 'running', ?, ?, NOW())"
        );
        $stmt->execute([$backupId, $etablissementId, $type, $targetInfo, $operatorUserId]);
        return (int)$this->pdo->lastInsertId();
    }

    private function logRestoreEnd(int $restoreId, string $statut, ?int $rowsRestored, float $startedAt, ?string $error = null, ?string $targetInfoOverride = null): void
    {
        $durationMs = (int)round((microtime(true) - $startedAt) * 1000);
        $sql = "UPDATE platform_restores SET statut = ?, rows_restored = ?, completed_at = NOW(), duration_ms = ?, error_message = ?";
        $params = [$statut, $rowsRestored, $durationMs, $error];
        if ($targetInfoOverride !== null) {
            $sql .= ", target_info = ?";
            $params[] = $targetInfoOverride;
        }
        $sql .= " WHERE id = ?";
        $params[] = $restoreId;
        $this->pdo->prepare($sql)->execute($params);
    }

    private function openRawConnection(?string $dbName): PDO
    {
        $config = require ROOT_PATH . '/config/database.php';
        $conn = $config['connections'][$config['default']];
        $dsn = sprintf(
            '%s:host=%s;port=%s%s;charset=%s',
            $conn['driver'], $conn['host'], $conn['port'],
            $dbName !== null ? ";dbname={$dbName}" : '',
            $conn['charset']
        );
        return new PDO($dsn, $conn['username'], $conn['password'], $conn['options']);
    }
}

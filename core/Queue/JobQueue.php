<?php

declare(strict_types=1);

namespace Core\Queue;

use Core\Database;
use PDO;

/**
 * File d'attente de tâches asynchrones tenant-scopée — MULTI_TENANT_V2_BLUEPRINT.md
 * (Phase 14.9, évolution de la ligne §14.9 "Cache Redis + Queue Workers").
 *
 * Backend : table `jobs` (MySQL), consommée par Core\Queue\QueueWorker.
 * Pas de worker démon Supervisord dans cet environnement (Windows/WAMP local,
 * sans process manager) — voir database/queue-worker.php (invocation
 * manuelle/planifiée) et MULTI_TENANT_CACHE_QUEUE_IMPLEMENTATION_REPORT.md
 * §"Hors périmètre".
 *
 * Chaque tâche transporte SYSTÉMATIQUEMENT son etablissement_id d'origine —
 * c'est ce que QueueWorker utilise pour rejouer le TenantContext correct
 * avant d'exécuter handle(), qu'elle soit traitée immédiatement ou des
 * heures plus tard par un worker qui a depuis traité des tâches d'autres
 * tenants entre-temps.
 */
final class JobQueue
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public static function make(): self
    {
        return new self(Database::getInstance()->getConnection());
    }

    /**
     * @param class-string<Job> $jobClass
     * @param array<string, mixed> $payload
     */
    public function push(string $jobClass, array $payload, ?int $etablissementId, int $delaySeconds = 0, int $maxAttempts = 3): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO jobs (etablissement_id, type, payload, status, max_attempts, run_at)
             VALUES (?, ?, ?, 'pending', ?, DATE_ADD(NOW(), INTERVAL ? SECOND))"
        );
        $stmt->execute([
            $etablissementId,
            $jobClass,
            json_encode($payload, JSON_THROW_ON_ERROR),
            $maxAttempts,
            max(0, $delaySeconds),
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Réserve atomiquement la prochaine tâche exécutable (run_at <= maintenant,
     * status='pending') et la passe en 'processing'. Retourne null s'il n'y a
     * rien à traiter.
     *
     * @return array{id:int, etablissement_id:?int, type:string, payload:array, attempts:int, max_attempts:int}|null
     */
    public function reserveNext(): ?array
    {
        // Réentrant : n'ouvre/ne referme sa propre transaction que si aucune
        // n'est déjà active (ex. tests qui enveloppent tout dans une seule
        // transaction annulée en fin de script) — PDO ne supporte pas les
        // transactions imbriquées, un beginTransaction() aveugle y échouerait.
        $ownsTransaction = !$this->pdo->inTransaction();
        if ($ownsTransaction) {
            $this->pdo->beginTransaction();
        }
        try {
            $stmt = $this->pdo->prepare(
                "SELECT * FROM jobs WHERE status = 'pending' AND run_at <= NOW()
                 ORDER BY run_at ASC LIMIT 1 FOR UPDATE"
            );
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row === false) {
                if ($ownsTransaction) {
                    $this->pdo->commit();
                }
                return null;
            }

            $upd = $this->pdo->prepare(
                "UPDATE jobs SET status = 'processing', started_at = NOW(), attempts = attempts + 1 WHERE id = ?"
            );
            $upd->execute([$row['id']]);
            if ($ownsTransaction) {
                $this->pdo->commit();
            }

            return [
                'id'               => (int)$row['id'],
                'etablissement_id' => $row['etablissement_id'] !== null ? (int)$row['etablissement_id'] : null,
                'type'             => $row['type'],
                'payload'          => json_decode($row['payload'], true, 512, JSON_THROW_ON_ERROR),
                'attempts'         => (int)$row['attempts'] + 1,
                'max_attempts'     => (int)$row['max_attempts'],
            ];
        } catch (\Throwable $e) {
            if ($ownsTransaction) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function markDone(int $jobId, float $durationMs): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE jobs SET status = 'done', completed_at = NOW(), duration_ms = ? WHERE id = ?"
        );
        $stmt->execute([(int)round($durationMs), $jobId]);
    }

    /**
     * Échec d'exécution : si le nombre d'essais restant le permet, la tâche
     * repasse en 'pending' avec un backoff exponentiel (2^attempts minutes,
     * plafonné à 30 min) ; sinon elle est marquée 'failed' définitivement.
     */
    public function markFailed(int $jobId, int $attempts, int $maxAttempts, string $error): void
    {
        if ($attempts < $maxAttempts) {
            $backoffMinutes = min(30, 2 ** $attempts);
            $stmt = $this->pdo->prepare(
                "UPDATE jobs SET status = 'pending', run_at = DATE_ADD(NOW(), INTERVAL ? MINUTE), error = ? WHERE id = ?"
            );
            $stmt->execute([$backoffMinutes, $error, $jobId]);
            return;
        }

        $stmt = $this->pdo->prepare(
            "UPDATE jobs SET status = 'failed', completed_at = NOW(), error = ? WHERE id = ?"
        );
        $stmt->execute([$error, $jobId]);
    }

    /**
     * @return array{pending:int, processing:int, done:int, failed:int, avg_duration_ms:?float}
     */
    public function stats(?int $etablissementId = null): array
    {
        $where = $etablissementId !== null ? "WHERE etablissement_id = ?" : "";
        $params = $etablissementId !== null ? [$etablissementId] : [];

        $stmt = $this->pdo->prepare("SELECT status, COUNT(*) AS n FROM jobs {$where} GROUP BY status");
        $stmt->execute($params);

        $counts = ['pending' => 0, 'processing' => 0, 'done' => 0, 'failed' => 0];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $counts[$row['status']] = (int)$row['n'];
        }

        $avgStmt = $this->pdo->prepare("SELECT AVG(duration_ms) FROM jobs {$where}" . ($etablissementId !== null ? " AND" : " WHERE") . " status = 'done'");
        $avgStmt->execute($params);
        $avg = $avgStmt->fetchColumn();

        return $counts + ['avg_duration_ms' => $avg !== null && $avg !== false ? (float)$avg : null];
    }

    /** @return list<array{id:int,type:string,status:string,attempts:int,error:?string,created_at:string,completed_at:?string}> */
    public function recent(?int $etablissementId, int $limit = 50): array
    {
        $where = $etablissementId !== null ? "WHERE etablissement_id = ?" : "";
        $params = $etablissementId !== null ? [$etablissementId] : [];
        $stmt = $this->pdo->prepare(
            "SELECT id, type, status, attempts, error, created_at, completed_at
             FROM jobs {$where} ORDER BY created_at DESC LIMIT " . max(1, min(500, $limit))
        );
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

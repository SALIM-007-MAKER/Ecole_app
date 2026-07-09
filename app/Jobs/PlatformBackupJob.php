<?php

declare(strict_types=1);

namespace App\Jobs;

use Core\Backup\BackupService;
use Core\Queue\Job;

/**
 * Sauvegarde planifiée — Phase 14.11 (blueprint §19.1, "sauvegarde
 * quotidienne 02h00 UTC"). Réutilise la file d'attente de la Phase 14.9 :
 * pousser une tâche `PlatformBackupJob` puis exécuter
 * database/queue-worker.php (manuellement ou via une tâche planifiée
 * Windows/cron, aucun démon persistant dans cet environnement — même
 * limite déjà documentée en Phase 14.9).
 *
 * Payload : {type: 'global'|'tenant'|'differential', etablissement_id?: int,
 *            operator_user_id: int, since?: string (ISO 8601, pour differential)}
 */
final class PlatformBackupJob implements Job
{
    public function __construct(private readonly ?BackupService $backups = null)
    {
    }

    public function handle(array $payload): void
    {
        $service = $this->backups ?? BackupService::make();
        $operatorUserId = (int)($payload['operator_user_id'] ?? 0);

        match ($payload['type'] ?? 'global') {
            'tenant' => $service->createTenant((int)$payload['etablissement_id'], $operatorUserId),
            'differential' => $service->createDifferential(
                isset($payload['etablissement_id']) ? (int)$payload['etablissement_id'] : null,
                new \DateTimeImmutable($payload['since'] ?? '-1 day'),
                $operatorUserId
            ),
            default => $service->createGlobal($operatorUserId, 'global'),
        };
    }
}

<?php

declare(strict_types=1);

namespace Core\Queue;

/**
 * Contrat d'une tâche exécutable par Core\Queue\QueueWorker.
 * Implémentations : voir app/Jobs/.
 */
interface Job
{
    /** @param array<string, mixed> $payload */
    public function handle(array $payload): void;
}

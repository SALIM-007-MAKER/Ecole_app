<?php

declare(strict_types=1);

namespace Core\Queue;

use Core\Tenant\TenantContext;

/**
 * Exécute les tâches de la file `jobs` — MULTI_TENANT_V2_BLUEPRINT.md
 * (Phase 14.9). Aucun démon Supervisord dans cet environnement : voir
 * database/queue-worker.php pour l'invocation manuelle/planifiée, et
 * MULTI_TENANT_CACHE_QUEUE_IMPLEMENTATION_REPORT.md §"Hors périmètre".
 *
 * Propagation du TenantContext : c'est ICI, et seulement ici, que le
 * TenantContext d'une tâche différée est reconstitué avant handle() — le
 * worker peut traiter des tâches de plusieurs établissements à la suite
 * (ex. notifications de l'établissement 3 puis export de l'établissement 7)
 * sans jamais laisser le contexte d'une tâche "fuiter" vers la suivante :
 * le contexte précédent (avant reserveNext()) est toujours restauré dans
 * un `finally`, que la tâche réussisse ou échoue.
 */
final class QueueWorker
{
    public function __construct(
        private readonly JobQueue $queue,
        /** @var array<class-string<Job>, Job> */
        private readonly array $jobResolver = [],
    ) {
    }

    public static function make(): self
    {
        return new self(JobQueue::make());
    }

    /** Traite une seule tâche. Retourne false s'il n'y avait rien à traiter. */
    public function processNext(): bool
    {
        $reserved = $this->queue->reserveNext();
        if ($reserved === null) {
            return false;
        }

        $previousId = TenantContext::current();
        $previousEtab = TenantContext::get();

        try {
            if ($reserved['etablissement_id'] !== null) {
                TenantContext::set($reserved['etablissement_id']);
            }

            $job = $this->resolveJob($reserved['type']);
            $start = microtime(true);
            $job->handle($reserved['payload']);
            $durationMs = (microtime(true) - $start) * 1000;

            $this->queue->markDone($reserved['id'], $durationMs);
        } catch (\Throwable $e) {
            $this->queue->markFailed($reserved['id'], $reserved['attempts'], $reserved['max_attempts'], $e->getMessage());
        } finally {
            // Restaure TOUJOURS le contexte précédent — jamais de fuite vers
            // la tâche suivante traitée par ce même worker.
            if ($previousId !== null) {
                TenantContext::set($previousId, $previousEtab);
            } else {
                TenantContext::clear();
            }
        }

        return true;
    }

    /** Traite jusqu'à $limit tâches, ou jusqu'à épuisement de la file. Retourne le nombre traité. */
    public function processBatch(int $limit = 50): int
    {
        $processed = 0;
        while ($processed < $limit && $this->processNext()) {
            $processed++;
        }
        return $processed;
    }

    /** @param class-string<Job> $type */
    private function resolveJob(string $type): Job
    {
        if (isset($this->jobResolver[$type])) {
            return $this->jobResolver[$type];
        }
        if (!class_exists($type) || !is_subclass_of($type, Job::class)) {
            throw new \RuntimeException("Classe de tâche invalide ou introuvable : {$type}");
        }
        return new $type();
    }
}

<?php

namespace App\Modules\Academique\Listeners;

use App\Modules\Academique\Events\EvaluationArchived;
use App\Modules\Academique\Events\EvaluationCreated;
use App\Modules\Academique\Events\EvaluationLocked;
use App\Modules\Academique\Events\EvaluationPublished;
use App\Modules\Academique\Events\EvaluationUpdated;
use App\Services\AuditService;
use Core\Event;
use Core\Listener;

class EvaluationHandler implements Listener
{
    private AuditService $audit;

    public function __construct()
    {
        $this->audit = new AuditService();
    }

    public function handle(Event $event): void
    {
        match(true) {
            $event instanceof EvaluationCreated   => $this->onCreated($event),
            $event instanceof EvaluationUpdated   => $this->onUpdated($event),
            $event instanceof EvaluationPublished => $this->onPublished($event),
            $event instanceof EvaluationLocked    => $this->onLocked($event),
            $event instanceof EvaluationArchived  => $this->onArchived($event),
            default                               => null,
        };
    }

    private function onCreated(EvaluationCreated $event): void
    {
        $this->audit->logCreate(
            $event->createdById,
            'academique',
            'evaluation',
            $event->evaluationId,
            $event->toArray()
        );
    }

    private function onUpdated(EvaluationUpdated $event): void
    {
        $this->audit->log(
            $event->updatedById,
            'update',
            'academique',
            'evaluation',
            $event->evaluationId,
            null,
            $event->changedFields
        );
    }

    private function onPublished(EvaluationPublished $event): void
    {
        $this->audit->log(
            $event->publishedById,
            'publier',
            'academique',
            'evaluation',
            $event->evaluationId,
            null,
            ['libelle' => $event->libelle, 'classe_id' => $event->classeId]
        );
    }

    private function onLocked(EvaluationLocked $event): void
    {
        $this->audit->log(
            $event->lockedById,
            'verrouiller',
            'academique',
            'evaluation',
            $event->evaluationId,
            null,
            ['libelle' => $event->libelle]
        );
    }

    private function onArchived(EvaluationArchived $event): void
    {
        $this->audit->logDelete(
            $event->archivedById,
            'academique',
            'evaluation',
            $event->evaluationId,
            $event->toArray()
        );
    }
}

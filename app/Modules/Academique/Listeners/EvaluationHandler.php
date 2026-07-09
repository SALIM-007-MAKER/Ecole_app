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
        (new AuditService())->logCreate(
            'evaluation',
            $event->evaluationId,
            $event->toArray(),
            $event->createdById
        );
    }

    private function onUpdated(EvaluationUpdated $event): void
    {
        (new AuditService())->log(
            'update',
            'evaluation',
            $event->evaluationId,
            $event->changedFields,
            $event->updatedById
        );
    }

    private function onPublished(EvaluationPublished $event): void
    {
        (new AuditService())->log(
            'publier',
            'evaluation',
            $event->evaluationId,
            ['libelle' => $event->libelle, 'classe_id' => $event->classeId],
            $event->publishedById
        );
    }

    private function onLocked(EvaluationLocked $event): void
    {
        (new AuditService())->log(
            'verrouiller',
            'evaluation',
            $event->evaluationId,
            ['libelle' => $event->libelle],
            $event->lockedById
        );
    }

    private function onArchived(EvaluationArchived $event): void
    {
        (new AuditService())->logDelete(
            'evaluation',
            $event->evaluationId,
            $event->toArray(),
            $event->archivedById
        );
    }
}

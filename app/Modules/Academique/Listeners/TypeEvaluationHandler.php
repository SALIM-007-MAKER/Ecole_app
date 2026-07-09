<?php

namespace App\Modules\Academique\Listeners;

use App\Modules\Academique\Events\EvaluationTypeActivated;
use App\Modules\Academique\Events\EvaluationTypeArchived;
use App\Modules\Academique\Events\EvaluationTypeCreated;
use App\Modules\Academique\Events\EvaluationTypeDeactivated;
use App\Modules\Academique\Events\EvaluationTypeUpdated;
use App\Services\AuditService;
use Core\Event;
use Core\Listener;

class TypeEvaluationHandler implements Listener
{
    public function handle(Event $event): void
    {
        match(true) {
            $event instanceof EvaluationTypeCreated     => $this->onCreated($event),
            $event instanceof EvaluationTypeUpdated     => $this->onUpdated($event),
            $event instanceof EvaluationTypeActivated   => $this->onActivated($event),
            $event instanceof EvaluationTypeDeactivated => $this->onDeactivated($event),
            $event instanceof EvaluationTypeArchived    => $this->onArchived($event),
            default                                     => null,
        };
    }

    private function onCreated(EvaluationTypeCreated $event): void
    {
        (new AuditService())->logCreate(
            'type_evaluation',
            $event->typeId,
            $event->toArray(),
            $event->createdById
        );
    }

    private function onUpdated(EvaluationTypeUpdated $event): void
    {
        (new AuditService())->log(
            'update',
            'type_evaluation',
            $event->typeId,
            $event->changedFields,
            $event->updatedById
        );
    }

    private function onActivated(EvaluationTypeActivated $event): void
    {
        (new AuditService())->log(
            'activer',
            'type_evaluation',
            $event->typeId,
            ['nom' => $event->nom, 'code' => $event->code],
            $event->activatedById
        );
    }

    private function onDeactivated(EvaluationTypeDeactivated $event): void
    {
        (new AuditService())->log(
            'desactiver',
            'type_evaluation',
            $event->typeId,
            ['nom' => $event->nom, 'code' => $event->code],
            $event->deactivatedById
        );
    }

    private function onArchived(EvaluationTypeArchived $event): void
    {
        (new AuditService())->logDelete(
            'type_evaluation',
            $event->typeId,
            $event->toArray(),
            $event->archivedById
        );
    }
}

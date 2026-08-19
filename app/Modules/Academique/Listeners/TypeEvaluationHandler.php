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
    private AuditService $audit;

    public function __construct()
    {
        $this->audit = new AuditService();
    }

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
        $this->audit->logCreate(
            $event->createdById,
            'academique',
            'type_evaluation',
            $event->typeId,
            $event->toArray()
        );
    }

    private function onUpdated(EvaluationTypeUpdated $event): void
    {
        $this->audit->log(
            $event->updatedById,
            'update',
            'academique',
            'type_evaluation',
            $event->typeId,
            null,
            $event->changedFields
        );
    }

    private function onActivated(EvaluationTypeActivated $event): void
    {
        $this->audit->log(
            $event->activatedById,
            'activer',
            'academique',
            'type_evaluation',
            $event->typeId,
            null,
            ['nom' => $event->nom, 'code' => $event->code]
        );
    }

    private function onDeactivated(EvaluationTypeDeactivated $event): void
    {
        $this->audit->log(
            $event->deactivatedById,
            'desactiver',
            'academique',
            'type_evaluation',
            $event->typeId,
            null,
            ['nom' => $event->nom, 'code' => $event->code]
        );
    }

    private function onArchived(EvaluationTypeArchived $event): void
    {
        $this->audit->logDelete(
            $event->archivedById,
            'academique',
            'type_evaluation',
            $event->typeId,
            $event->toArray()
        );
    }
}

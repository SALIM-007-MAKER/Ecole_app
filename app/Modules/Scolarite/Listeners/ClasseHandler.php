<?php

namespace App\Modules\Scolarite\Listeners;

use App\Modules\Scolarite\Events\ClasseCreated;
use App\Modules\Scolarite\Events\ClasseUpdated;
use App\Modules\Scolarite\Events\ClasseDeleted;
use App\Modules\Scolarite\Events\EleveAssignedToClasse;
use App\Modules\Scolarite\Events\EleveRemovedFromClasse;
use App\Services\AuditService;
use Core\Event;
use Core\Listener;

class ClasseHandler implements Listener
{
    private AuditService $audit;

    public function __construct()
    {
        $this->audit = new AuditService();
    }

    public function handle(Event $event): void
    {
        match (true) {
            $event instanceof ClasseCreated           => $this->onClasseCreated($event),
            $event instanceof ClasseUpdated           => $this->onClasseUpdated($event),
            $event instanceof ClasseDeleted           => $this->onClasseDeleted($event),
            $event instanceof EleveAssignedToClasse   => $this->onEleveAssigned($event),
            $event instanceof EleveRemovedFromClasse  => $this->onEleveRemoved($event),
            default => null,
        };
    }

    private function onClasseCreated(ClasseCreated $event): void
    {
        $this->audit->logCreate(
            $event->createdById,
            'scolarite',
            'classe',
            $event->classeId,
            $event->toArray(),
        );
    }

    private function onClasseUpdated(ClasseUpdated $event): void
    {
        $this->audit->log(
            $event->updatedById,
            'update',
            'scolarite',
            'classe',
            $event->classeId,
            $event->changedFields['avant'] ?? null,
            $event->changedFields['apres'] ?? null,
        );
    }

    private function onClasseDeleted(ClasseDeleted $event): void
    {
        $this->audit->logDelete(
            $event->deletedById,
            'scolarite',
            'classe',
            $event->classeId,
            ['nom' => $event->nom],
        );
    }

    private function onEleveAssigned(EleveAssignedToClasse $event): void
    {
        $this->audit->log(
            $event->assignedById,
            'affecter',
            'scolarite',
            'eleve',
            $event->eleveId,
            null,
            ['classe_id' => $event->classeId],
        );
    }

    private function onEleveRemoved(EleveRemovedFromClasse $event): void
    {
        $this->audit->log(
            $event->removedById,
            'retirer',
            'scolarite',
            'eleve',
            $event->eleveId,
            ['classe_id' => $event->classeId],
            null,
        );
    }
}

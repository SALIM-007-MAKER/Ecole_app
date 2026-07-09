<?php

namespace App\Modules\Scolarite\Listeners;

use Core\Event;
use Core\Listener;
use App\Modules\Scolarite\Events\ParentCreated;
use App\Modules\Scolarite\Events\ParentUpdated;
use App\Modules\Scolarite\Events\ParentLinkedToStudent;
use App\Modules\Scolarite\Events\ParentUnlinkedFromStudent;
use App\Modules\Scolarite\Events\EmergencyContactUpdated;
use App\Services\AuditService;

class FamilleHandler implements Listener
{
    private AuditService $audit;

    public function __construct()
    {
        $this->audit = new AuditService();
    }

    public function handle(Event $event): void
    {
        match (true) {
            $event instanceof ParentCreated              => $this->onCreated($event),
            $event instanceof ParentUpdated              => $this->onUpdated($event),
            $event instanceof ParentLinkedToStudent      => $this->onLinked($event),
            $event instanceof ParentUnlinkedFromStudent  => $this->onUnlinked($event),
            $event instanceof EmergencyContactUpdated    => $this->onUrgenceUpdated($event),
            default                                      => null,
        };
    }

    private function onCreated(ParentCreated $e): void
    {
        $this->audit->logCreate(
            'familles',
            $e->familleId,
            ['nom' => $e->nom],
            $e->createdById
        );
    }

    private function onUpdated(ParentUpdated $e): void
    {
        $this->audit->logUpdate(
            'familles',
            $e->familleId,
            $e->changedFields,
            $e->updatedById
        );
    }

    private function onLinked(ParentLinkedToStudent $e): void
    {
        $this->audit->log(
            'familles_eleves',
            $e->familleId,
            'link',
            ['eleve_id' => $e->eleveId, 'lien_parente' => $e->lienParente],
            $e->linkedById
        );
    }

    private function onUnlinked(ParentUnlinkedFromStudent $e): void
    {
        $this->audit->log(
            'familles_eleves',
            $e->familleId,
            'unlink',
            ['eleve_id' => $e->eleveId],
            $e->unlinkedById
        );
    }

    private function onUrgenceUpdated(EmergencyContactUpdated $e): void
    {
        $this->audit->log(
            'familles',
            $e->familleId,
            'emergency_contact_updated',
            [],
            $e->updatedById
        );
    }
}

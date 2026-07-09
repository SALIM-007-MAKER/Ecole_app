<?php

namespace App\Modules\Scolarite\Listeners;

use Core\Event;
use Core\Listener;
use App\Services\AuditService;
use App\Modules\Scolarite\Events\EleveUpdated;
use App\Modules\Scolarite\Events\EleveArchived;

class EleveHandler implements Listener
{
    private AuditService $audit;

    public function __construct()
    {
        $this->audit = new AuditService();
    }

    public function handle(Event $event): void
    {
        match (true) {
            $event instanceof EleveUpdated  => $this->onEleveUpdated($event),
            $event instanceof EleveArchived => $this->onEleveArchived($event),
            default                         => null,
        };
    }

    private function onEleveUpdated(EleveUpdated $event): void
    {
        $this->audit->log(
            $event->updatedById,
            'update',
            'scolarite',
            'eleve',
            $event->eleveId,
            $event->changedFields['avant'] ?? null,
            $event->changedFields['apres'] ?? null,
        );
    }

    private function onEleveArchived(EleveArchived $event): void
    {
        $this->audit->log(
            $event->archivedById,
            'archive',
            'scolarite',
            'eleve',
            $event->eleveId,
            null,
            ['motif' => $event->motif],
        );
    }
}

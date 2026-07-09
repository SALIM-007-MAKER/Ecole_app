<?php

namespace App\Modules\Finance\Listeners;

use App\Modules\Finance\Events\FeeActivated;
use App\Modules\Finance\Events\FeeArchived;
use App\Modules\Finance\Events\FeeCreated;
use App\Modules\Finance\Events\FeeDeactivated;
use App\Modules\Finance\Events\FeeUpdated;
use App\Services\AuditService;
use Core\Event;
use Core\Listener;

class FraisAuditHandler implements Listener
{
    private AuditService $audit;

    public function __construct()
    {
        $this->audit = new AuditService();
    }

    public function handle(Event $event): void
    {
        match (true) {
            $event instanceof FeeCreated     => $this->onCreated($event),
            $event instanceof FeeUpdated     => $this->onUpdated($event),
            $event instanceof FeeActivated   => $this->onActivated($event),
            $event instanceof FeeDeactivated => $this->onDeactivated($event),
            $event instanceof FeeArchived    => $this->onArchived($event),
            default                          => null,
        };
    }

    private function onCreated(FeeCreated $event): void
    {
        $this->audit->logCreate(
            $event->createdById,
            'finance',
            'frais_type',
            $event->fraisTypeId,
            $event->toArray(),
        );
    }

    private function onUpdated(FeeUpdated $event): void
    {
        $this->audit->log(
            $event->updatedById,
            'update',
            'finance',
            'frais_type',
            $event->fraisTypeId,
            array_map(fn($v) => $v['avant'], $event->changedFields),
            array_map(fn($v) => $v['apres'], $event->changedFields),
        );
    }

    private function onActivated(FeeActivated $event): void
    {
        $this->audit->log(
            $event->activatedById,
            'activer',
            'finance',
            'frais_type',
            $event->fraisTypeId,
            ['statut' => 'inactif'],
            ['statut' => 'actif', 'nom' => $event->nom],
        );
    }

    private function onDeactivated(FeeDeactivated $event): void
    {
        $this->audit->log(
            $event->deactivatedById,
            'desactiver',
            'finance',
            'frais_type',
            $event->fraisTypeId,
            ['statut' => 'actif'],
            ['statut' => 'inactif', 'nom' => $event->nom],
        );
    }

    private function onArchived(FeeArchived $event): void
    {
        $this->audit->logDelete(
            $event->archivedById,
            'finance',
            'frais_type',
            $event->fraisTypeId,
            ['nom' => $event->nom, 'motif' => $event->motif],
        );
    }
}

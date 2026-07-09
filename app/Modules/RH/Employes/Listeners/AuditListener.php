<?php

namespace App\Modules\RH\Employes\Listeners;

use App\Modules\RH\Employes\Events\EmployeeArchived;
use App\Modules\RH\Employes\Events\EmployeeCreated;
use App\Modules\RH\Employes\Events\EmployeeRestored;
use App\Modules\RH\Employes\Events\EmployeeUpdated;
use App\Services\AuditService;
use Core\Event;
use Core\Listener;

class AuditListener implements Listener
{
    private AuditService $audit;

    public function __construct()
    {
        $this->audit = new AuditService();
    }

    public function handle(Event $event): void
    {
        match (true) {
            $event instanceof EmployeeCreated  => $this->onCreated($event),
            $event instanceof EmployeeUpdated  => $this->onUpdated($event),
            $event instanceof EmployeeArchived => $this->onArchived($event),
            $event instanceof EmployeeRestored => $this->onRestored($event),
            default                            => null,
        };
    }

    private function onCreated(EmployeeCreated $event): void
    {
        $this->audit->logCreate(
            $event->creeParId,
            'rh',
            'employe',
            $event->employeId,
            $event->toArray()
        );
    }

    private function onUpdated(EmployeeUpdated $event): void
    {
        $this->audit->log(
            $event->modifieParId,
            'update',
            'rh',
            'employe',
            $event->employeId,
            null,
            $event->changes
        );
    }

    private function onArchived(EmployeeArchived $event): void
    {
        $this->audit->logDelete(
            $event->archiveParId,
            'rh',
            'employe',
            $event->employeId,
            $event->toArray()
        );
    }

    private function onRestored(EmployeeRestored $event): void
    {
        $this->audit->log(
            $event->restaureParId,
            'restore',
            'rh',
            'employe',
            $event->employeId
        );
    }
}

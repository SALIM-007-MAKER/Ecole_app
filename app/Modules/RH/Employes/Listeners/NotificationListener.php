<?php

namespace App\Modules\RH\Employes\Listeners;

use App\Modules\RH\Employes\Events\EmployeeArchived;
use App\Modules\RH\Employes\Events\EmployeeCreated;
use App\Modules\RH\Employes\Events\EmployeeRestored;
use App\Modules\RH\Employes\Events\EmployeeUpdated;
use Core\Event;
use Core\Listener;
use Core\Logger;

class NotificationListener implements Listener
{
    public function handle(Event $event): void
    {
        match (true) {
            $event instanceof EmployeeCreated  => $this->onCreated($event),
            $event instanceof EmployeeArchived => $this->onArchived($event),
            $event instanceof EmployeeRestored => $this->onRestored($event),
            $event instanceof EmployeeUpdated  => null,
            default                            => null,
        };
    }

    private function onCreated(EmployeeCreated $event): void
    {
        // MS2-M-004 : NotificationService RH triggers à implémenter en V2.1
        // Notifier : admin + directeur → nouveau membre du personnel enregistré
        Logger::info(sprintf(
            '[RH] Employé créé — id:%d matricule:%s type:%s nom:%s %s',
            $event->employeId,
            $event->matricule,
            $event->typePersonnel,
            $event->prenom,
            $event->nom,
        ));
    }

    private function onArchived(EmployeeArchived $event): void
    {
        // MS2-M-004 : Notifier admin + directeur → archivage employé
        Logger::info(sprintf(
            '[RH] Employé archivé — id:%d matricule:%s',
            $event->employeId,
            $event->matricule,
        ));
    }

    private function onRestored(EmployeeRestored $event): void
    {
        Logger::info(sprintf(
            '[RH] Employé restauré — id:%d matricule:%s',
            $event->employeId,
            $event->matricule,
        ));
    }
}

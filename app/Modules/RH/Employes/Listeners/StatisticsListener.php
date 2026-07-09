<?php

namespace App\Modules\RH\Employes\Listeners;

use App\Modules\RH\Employes\Events\EmployeeArchived;
use App\Modules\RH\Employes\Events\EmployeeCreated;
use App\Modules\RH\Employes\Events\EmployeeRestored;
use Core\Event;
use Core\Listener;
use Core\Logger;

class StatisticsListener implements Listener
{
    public function handle(Event $event): void
    {
        match (true) {
            $event instanceof EmployeeCreated  => $this->onCreated($event),
            $event instanceof EmployeeArchived => $this->onArchived($event),
            $event instanceof EmployeeRestored => $this->onRestored($event),
            default                            => null,
        };
    }

    private function onCreated(EmployeeCreated $event): void
    {
        // Futur : invalider cache KPI effectifs sur tableau de bord RH
        Logger::info(sprintf(
            '[RH][Stats] Effectif +1 — type:%s',
            $event->typePersonnel,
        ));
    }

    private function onArchived(EmployeeArchived $event): void
    {
        Logger::info(sprintf(
            '[RH][Stats] Effectif -1 — id:%d',
            $event->employeId,
        ));
    }

    private function onRestored(EmployeeRestored $event): void
    {
        Logger::info(sprintf(
            '[RH][Stats] Effectif +1 (restauré) — id:%d',
            $event->employeId,
        ));
    }
}

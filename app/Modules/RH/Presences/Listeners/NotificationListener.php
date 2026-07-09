<?php

declare(strict_types=1);

namespace App\Modules\RH\Presences\Listeners;

use Core\Listener;
use Core\Event;
use App\Modules\RH\Presences\Events\AttendanceLateDetected;
use App\Modules\RH\Presences\Events\AttendanceValidated;

class NotificationListener implements Listener
{
    public function handle(Event $event): void
    {
        // Stub Phase 6.10 — Notifications RH Présences
        // Priorité : retards (email superviseur) + rejet de validation (email employé)
        match (true) {
            $event instanceof AttendanceLateDetected => null, // TODO: notifier superviseur
            $event instanceof AttendanceValidated    => null, // TODO: notifier employé si rejeté
            default                                  => null,
        };
    }
}

<?php

namespace App\Modules\VieScolaire\Presences\Listeners;

use App\Modules\VieScolaire\Presences\Events\AttendanceValidated;
use App\Modules\VieScolaire\Presences\Events\StudentAbsent;
use App\Modules\VieScolaire\Presences\Events\StudentLate;
use Core\Event;
use Core\Listener;
use Core\Logger;

/**
 * Notifications parents/enseignants depuis les événements d'appel.
 * La connexion avec NotificationService V2 est différée (MS2-M-004).
 */
class NotificationListener implements Listener
{
    public function handle(Event $event): void
    {
        match (true) {
            $event instanceof StudentAbsent       => $this->notifyAbsence($event),
            $event instanceof StudentLate         => $this->notifyRetard($event),
            $event instanceof AttendanceValidated => $this->notifySessionValidee($event),
            default                               => null,
        };
    }

    private function notifyAbsence(StudentAbsent $e): void
    {
        // Notification parent différée — MS2-M-004
        Logger::info(sprintf(
            '[VieScolaire/Notif] Notification absence différée — élève:%d date:%s',
            $e->eleveId, $e->dateAppel,
        ));
    }

    private function notifyRetard(StudentLate $e): void
    {
        // Notification parent différée — MS2-M-004
        Logger::info(sprintf(
            '[VieScolaire/Notif] Notification retard différée — élève:%d',
            $e->eleveId,
        ));
    }

    private function notifySessionValidee(AttendanceValidated $e): void
    {
        // Notification enseignant/admin différée — MS2-M-004
        Logger::info(sprintf(
            '[VieScolaire/Notif] Session validée — appel:%d taux présence:%d%%',
            $e->appelId,
            $e->totalEleves > 0
                ? (int)round($e->totalPresents / $e->totalEleves * 100)
                : 0,
        ));
    }
}

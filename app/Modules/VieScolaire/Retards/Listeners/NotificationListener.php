<?php

namespace App\Modules\VieScolaire\Retards\Listeners;

use App\Modules\VieScolaire\Retards\Events\LateJustified;
use App\Modules\VieScolaire\Retards\Events\LateRejected;
use App\Modules\VieScolaire\Retards\Events\LateThresholdReached;
use App\Modules\VieScolaire\Retards\Events\StudentLate;
use Core\Event;
use Core\Listener;
use Core\Logger;

/**
 * Notifications différées — connexion à NotificationService V2 (MS2-M-004).
 */
class NotificationListener implements Listener
{
    public function handle(Event $event): void
    {
        match (true) {
            $event instanceof LateThresholdReached => $this->onThreshold($event),
            $event instanceof LateJustified        => $this->onJustified($event),
            $event instanceof LateRejected         => $this->onRejected($event),
            $event instanceof StudentLate          => $this->onStudentLate($event),
            default                                => null,
        };
    }

    private function onStudentLate(StudentLate $e): void
    {
        // MS2-M-004 — Notifier parent : retard élève
        Logger::info(sprintf(
            '[Retards/Notif] [DEFERRED] notif parent élève:%d retard:%dmin',
            $e->eleveId, $e->retardMinutes,
        ));
    }

    private function onThreshold(LateThresholdReached $e): void
    {
        // MS2-M-004 — Alerte directeur/secrétaire : seuil retards atteint
        Logger::warning(sprintf(
            '[Retards/Notif] [DEFERRED] alerte seuil — élève:%d total:%d',
            $e->eleveId, $e->totalRetards,
        ));
    }

    private function onJustified(LateJustified $e): void
    {
        Logger::info(sprintf(
            '[Retards/Notif] [DEFERRED] notif justification validée — élève:%d retard:%d',
            $e->eleveId, $e->retardId,
        ));
    }

    private function onRejected(LateRejected $e): void
    {
        Logger::info(sprintf(
            '[Retards/Notif] [DEFERRED] notif justification refusée — élève:%d retard:%d',
            $e->eleveId, $e->retardId,
        ));
    }
}

<?php

namespace App\Modules\RH\Enseignants\Listeners;

use App\Modules\RH\Enseignants\Events\TeacherAssigned;
use App\Modules\RH\Enseignants\Events\TeacherCreated;
use App\Modules\RH\Enseignants\Events\TeacherUpdated;
use Core\Event;
use Core\Listener;
use Core\Logger;

class StatisticsListener implements Listener
{
    public function handle(Event $event): void
    {
        match(true) {
            $event instanceof TeacherCreated  => $this->invalidate($event->enseignantId, 'created'),
            $event instanceof TeacherUpdated  => $this->invalidate($event->enseignantId, 'updated'),
            $event instanceof TeacherAssigned => $this->invalidate($event->enseignantId, 'assigned'),
            default                           => null,
        };
    }

    private function invalidate(int $id, string $reason): void
    {
        // Stub — invalidation cache statistiques RH Dashboard (Phase 6.8)
        Logger::info('RH stats cache invalidation stub', ['enseignant_id' => $id, 'reason' => $reason]);
    }
}

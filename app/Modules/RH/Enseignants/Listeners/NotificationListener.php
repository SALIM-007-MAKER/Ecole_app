<?php

namespace App\Modules\RH\Enseignants\Listeners;

use App\Modules\RH\Enseignants\Events\TeacherAssigned;
use App\Modules\RH\Enseignants\Events\TeacherCreated;
use Core\Event;
use Core\Listener;
use Core\Logger;

class NotificationListener implements Listener
{
    public function handle(Event $event): void
    {
        match(true) {
            $event instanceof TeacherCreated  => $this->onCreated($event),
            $event instanceof TeacherAssigned => $this->onAssigned($event),
            default                           => null,
        };
    }

    private function onCreated(TeacherCreated $e): void
    {
        // MS2-M-004 : triggers V2 NotificationService à définir en Phase 6.8
        Logger::info('TeacherCreated notification stub', [
            'enseignant_id' => $e->enseignantId,
            'matricule'     => $e->matricule,
        ]);
    }

    private function onAssigned(TeacherAssigned $e): void
    {
        Logger::info('TeacherAssigned notification stub', [
            'enseignant_id' => $e->enseignantId,
            'nb_matieres'   => count($e->matieres),
        ]);
    }
}

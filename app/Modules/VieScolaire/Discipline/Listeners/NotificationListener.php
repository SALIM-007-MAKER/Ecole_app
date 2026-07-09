<?php

namespace App\Modules\VieScolaire\Discipline\Listeners;

use App\Modules\VieScolaire\Discipline\Events\DisciplineAppealSubmitted;
use App\Modules\VieScolaire\Discipline\Events\DisciplineCaseClosed;
use App\Modules\VieScolaire\Discipline\Events\DisciplineCaseCreated;
use App\Modules\VieScolaire\Discipline\Events\DisciplinaryActionAssigned;
use Core\Event;
use Core\Listener;
use Core\Logger;

// MS2-M-004: brancher NotificationService V2 quand disponible
class NotificationListener implements Listener
{
    public function handle(Event $event): void
    {
        if ($event instanceof DisciplineCaseCreated) {
            Logger::info('discipline.notification', [
                'type'       => 'incident_signale',
                'gravite'    => $event->gravite,
                'eleve_id'   => $event->eleveId,
                'deferred'   => true,
            ]);
        } elseif ($event instanceof DisciplinaryActionAssigned) {
            Logger::info('discipline.notification', [
                'type'         => 'sanction_prononcee',
                'type_sanction'=> $event->typeSanction,
                'eleve_id'     => $event->eleveId,
                'deferred'     => true,
            ]);
        } elseif ($event instanceof DisciplineCaseClosed) {
            Logger::info('discipline.notification', [
                'type'    => 'dossier_clos',
                'eleve_id'=> $event->eleveId,
                'deferred'=> true,
            ]);
        } elseif ($event instanceof DisciplineAppealSubmitted) {
            Logger::info('discipline.notification', [
                'type'       => 'appel_soumis',
                'sanction_id'=> $event->sanctionId,
                'eleve_id'   => $event->eleveId,
                'deferred'   => true,
            ]);
        }
    }
}

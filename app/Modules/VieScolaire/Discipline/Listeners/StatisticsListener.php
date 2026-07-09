<?php

namespace App\Modules\VieScolaire\Discipline\Listeners;

use App\Modules\VieScolaire\Discipline\Events\DisciplineCaseCreated;
use App\Modules\VieScolaire\Discipline\Repositories\DisciplineRepository;
use Core\Event;
use Core\Listener;
use Core\Logger;

class StatisticsListener implements Listener
{
    public function handle(Event $event): void
    {
        if (!($event instanceof DisciplineCaseCreated)) {
            return;
        }

        try {
            $repo  = new DisciplineRepository();
            $total = $repo->countIncidentsByEleveAndAnnee(
                $event->eleveId,
                $event->anneeScolaire
            );

            Logger::info('discipline.statistics', [
                'action'        => 'incident_comptabilise',
                'eleve_id'      => $event->eleveId,
                'annee'         => $event->anneeScolaire,
                'total_incidents'=> $total,
                'gravite'       => $event->gravite,
            ]);
        } catch (\Throwable $e) {
            Logger::warning('discipline.statistics.error', [
                'message'  => $e->getMessage(),
                'eleve_id' => $event->eleveId,
            ]);
        }
    }
}

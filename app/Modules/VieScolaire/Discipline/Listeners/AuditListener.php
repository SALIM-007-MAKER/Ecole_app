<?php

namespace App\Modules\VieScolaire\Discipline\Listeners;

use App\Modules\VieScolaire\Discipline\Events\DisciplineAppealSubmitted;
use App\Modules\VieScolaire\Discipline\Events\DisciplineCaseClosed;
use App\Modules\VieScolaire\Discipline\Events\DisciplineCaseCreated;
use App\Modules\VieScolaire\Discipline\Events\DisciplinaryActionAssigned;
use Core\Event;
use Core\Listener;
use Core\Logger;

class AuditListener implements Listener
{
    public function handle(Event $event): void
    {
        if ($event instanceof DisciplineCaseCreated) {
            Logger::info('discipline.audit', [
                'action'     => 'incident_signale',
                'incident_id'=> $event->incidentId,
                'dossier_id' => $event->dossierId,
                'eleve_id'   => $event->eleveId,
                'gravite'    => $event->gravite,
                'categorie'  => $event->categorieCode,
                'signale_par'=> $event->signaleParId,
            ]);
        } elseif ($event instanceof DisciplinaryActionAssigned) {
            Logger::warning('discipline.audit', [
                'action'       => 'sanction_prononcee',
                'sanction_id'  => $event->sanctionId,
                'dossier_id'   => $event->dossierId,
                'eleve_id'     => $event->eleveId,
                'type_sanction'=> $event->typeSanction,
                'prononce_par' => $event->prononceParId,
            ]);
        } elseif ($event instanceof DisciplineCaseClosed) {
            Logger::info('discipline.audit', [
                'action'      => 'dossier_clos',
                'dossier_id'  => $event->dossierId,
                'eleve_id'    => $event->eleveId,
                'nb_incidents'=> $event->nbIncidentsTotal,
                'clos_par'    => $event->closParId,
            ]);
        } elseif ($event instanceof DisciplineAppealSubmitted) {
            Logger::info('discipline.audit', [
                'action'     => 'appel_soumis',
                'appel_id'   => $event->appelId,
                'sanction_id'=> $event->sanctionId,
                'eleve_id'   => $event->eleveId,
                'depose_par' => $event->deposePar,
            ]);
        }
    }
}

<?php

namespace App\Modules\VieScolaire\Discipline\Listeners;

use App\Modules\VieScolaire\Discipline\Events\DisciplineAppealSubmitted;
use App\Modules\VieScolaire\Discipline\Events\DisciplineCaseClosed;
use App\Modules\VieScolaire\Discipline\Events\DisciplineCaseCreated;
use App\Modules\VieScolaire\Discipline\Events\DisciplinaryActionAssigned;
use App\Services\AuditService;
use Core\Event;
use Core\Listener;
use Core\Logger;

/**
 * Journal de la discipline : fichier texte (historique) ET journal d'audit de l'établissement (table audit_logs, par établissement).
 * Identifiants uniquement : aucun nom d'élève dans la trace.
 */
class AuditListener implements Listener
{
    private AuditService $audit;

    public function __construct()
    {
        $this->audit = new AuditService();
    }

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
            $this->audit->log($event->signaleParId, 'incident_signale', 'vie_scolaire', 'incident_discipline', $event->incidentId, null, [
                'dossier_id' => $event->dossierId, 'eleve_id' => $event->eleveId, 'classe_id' => $event->classeId,
                'gravite' => $event->gravite, 'categorie' => $event->categorieCode,
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
            $this->audit->log($event->prononceParId, 'sanction_prononcee', 'vie_scolaire', 'sanction', $event->sanctionId, null, [
                'dossier_id' => $event->dossierId, 'eleve_id' => $event->eleveId, 'type_sanction' => $event->typeSanction, 'date_sanction' => $event->dateSanction,
            ]);
        } elseif ($event instanceof DisciplineCaseClosed) {
            Logger::info('discipline.audit', [
                'action'      => 'dossier_clos',
                'dossier_id'  => $event->dossierId,
                'eleve_id'    => $event->eleveId,
                'nb_incidents'=> $event->nbIncidentsTotal,
                'clos_par'    => $event->closParId,
            ]);
            $this->audit->log($event->closParId, 'dossier_clos', 'vie_scolaire', 'dossier_disciplinaire', $event->dossierId, null, [
                'eleve_id' => $event->eleveId, 'classe_id' => $event->classeId, 'nb_incidents' => $event->nbIncidentsTotal,
            ]);
        } elseif ($event instanceof DisciplineAppealSubmitted) {
            Logger::info('discipline.audit', [
                'action'     => 'appel_soumis',
                'appel_id'   => $event->appelId,
                'sanction_id'=> $event->sanctionId,
                'eleve_id'   => $event->eleveId,
                'depose_par' => $event->deposePar,
            ]);
            $this->audit->log($event->deposePar, 'appel_soumis', 'vie_scolaire', 'appel_sanction', $event->appelId, null, [
                'sanction_id' => $event->sanctionId, 'dossier_id' => $event->dossierId, 'eleve_id' => $event->eleveId,
            ]);
        }
    }
}

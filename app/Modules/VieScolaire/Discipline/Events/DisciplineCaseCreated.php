<?php

namespace App\Modules\VieScolaire\Discipline\Events;

use Core\Event;

/**
 * Déclenché à chaque nouvel incident disciplinaire signalé.
 * Le dossier peut être nouveau (premier incident) ou existant.
 */
class DisciplineCaseCreated extends Event
{
    public function __construct(
        public readonly int    $incidentId,
        public readonly int    $dossierId,
        public readonly int    $eleveId,
        public readonly int    $classeId,
        public readonly string $gravite,
        public readonly string $categorieCode,
        public readonly string $anneeScolaire,
        public readonly int    $signaleParId,
        public readonly bool   $premierIncident,
    ) {
        parent::__construct();
    }

    public function toArray(): array
    {
        return [
            'incident_id'      => $this->incidentId,
            'dossier_id'       => $this->dossierId,
            'eleve_id'         => $this->eleveId,
            'classe_id'        => $this->classeId,
            'gravite'          => $this->gravite,
            'categorie_code'   => $this->categorieCode,
            'annee_scolaire'   => $this->anneeScolaire,
            'signale_par_id'   => $this->signaleParId,
            'premier_incident' => $this->premierIncident,
            'fired_at'         => $this->getFiredAt(),
        ];
    }
}

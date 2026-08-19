<?php

namespace App\Modules\VieScolaire\Discipline\Events;

use Core\Event;

class DisciplineCaseClosed extends Event
{
    public function __construct(
        public readonly int    $dossierId,
        public readonly int    $eleveId,
        public readonly int    $classeId,
        public readonly string $anneeScolaire,
        public readonly int    $nbIncidentsTotal,
        public readonly int    $closParId,
    ) {
        parent::__construct();
    }

    public function toArray(): array
    {
        return [
            'dossier_id'         => $this->dossierId,
            'eleve_id'           => $this->eleveId,
            'classe_id'          => $this->classeId,
            'annee_scolaire'     => $this->anneeScolaire,
            'nb_incidents_total' => $this->nbIncidentsTotal,
            'clos_par_id'        => $this->closParId,
            'fired_at'           => $this->getFiredAt(),
        ];
    }
}

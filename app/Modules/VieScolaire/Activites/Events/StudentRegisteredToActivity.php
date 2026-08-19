<?php

namespace App\Modules\VieScolaire\Activites\Events;

use Core\Event;

class StudentRegisteredToActivity extends Event
{
    public function __construct(
        public readonly int    $inscriptionId,
        public readonly int    $activityId,
        public readonly int    $eleveId,
        public readonly string $statut,       // inscrit | liste_attente
        public readonly string $anneeScolaire,
        public readonly int    $inscritParId,
    ) {
        parent::__construct();
    }

    public function toArray(): array
    {
        return [
            'inscription_id' => $this->inscriptionId,
            'activity_id'    => $this->activityId,
            'eleve_id'       => $this->eleveId,
            'statut'         => $this->statut,
            'annee_scolaire' => $this->anneeScolaire,
            'inscrit_par_id' => $this->inscritParId,
            'fired_at'       => $this->getFiredAt(),
        ];
    }
}

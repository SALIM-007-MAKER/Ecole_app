<?php

namespace App\Modules\VieScolaire\Activites\Events;

use Core\Event;

class ActivityCancelled extends Event
{
    public function __construct(
        public readonly int    $activityId,
        public readonly string $titre,
        public readonly string $anneeScolaire,
        public readonly string $motif,
        public readonly int    $annuleParId,
    ) {
        parent::__construct();
    }

    public function toArray(): array
    {
        return [
            'activity_id'    => $this->activityId,
            'titre'          => $this->titre,
            'annee_scolaire' => $this->anneeScolaire,
            'motif'          => $this->motif,
            'annule_par_id'  => $this->annuleParId,
            'fired_at'       => $this->getFiredAt(),
        ];
    }
}

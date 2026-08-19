<?php

namespace App\Modules\VieScolaire\Activites\Events;

use Core\Event;

class ActivityPublished extends Event
{
    public function __construct(
        public readonly int    $activityId,
        public readonly string $titre,
        public readonly string $dateActivite,
        public readonly string $anneeScolaire,
        public readonly int    $publieParId,
    ) {
        parent::__construct();
    }

    public function toArray(): array
    {
        return [
            'activity_id'    => $this->activityId,
            'titre'          => $this->titre,
            'date_activite'  => $this->dateActivite,
            'annee_scolaire' => $this->anneeScolaire,
            'publie_par_id'  => $this->publieParId,
            'fired_at'       => $this->getFiredAt(),
        ];
    }
}

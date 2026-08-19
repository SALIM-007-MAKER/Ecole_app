<?php

namespace App\Modules\VieScolaire\Activites\Events;

use Core\Event;

class ActivityCreated extends Event
{
    public function __construct(
        public readonly int    $activityId,
        public readonly int    $categorieId,
        public readonly string $titre,
        public readonly string $dateActivite,
        public readonly string $anneeScolaire,
        public readonly int    $creeParId,
    ) {
        parent::__construct();
    }

    public function toArray(): array
    {
        return [
            'activity_id'    => $this->activityId,
            'categorie_id'   => $this->categorieId,
            'titre'          => $this->titre,
            'date_activite'  => $this->dateActivite,
            'annee_scolaire' => $this->anneeScolaire,
            'cree_par_id'    => $this->creeParId,
            'fired_at'       => $this->getFiredAt(),
        ];
    }
}

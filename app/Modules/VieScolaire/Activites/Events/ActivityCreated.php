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
    ) {}
}

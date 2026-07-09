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
    ) {}
}

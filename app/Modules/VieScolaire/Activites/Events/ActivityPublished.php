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
    ) {}
}

<?php

namespace App\Modules\VieScolaire\Activites\Events;

use Core\Event;

class ActivityUpdated extends Event
{
    public function __construct(
        public readonly int    $activityId,
        public readonly string $anneeScolaire,
        public readonly string $action,
        public readonly int    $modifieParId,
    ) {}
}

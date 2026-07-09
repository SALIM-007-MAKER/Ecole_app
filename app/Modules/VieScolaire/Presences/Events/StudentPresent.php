<?php

namespace App\Modules\VieScolaire\Presences\Events;

use Core\Event;

class StudentPresent extends Event
{
    public function __construct(
        public readonly int    $appelId,
        public readonly int    $eleveId,
        public readonly int    $classeId,
        public readonly string $dateAppel,
        public readonly int    $saisieParId,
    ) {}
}

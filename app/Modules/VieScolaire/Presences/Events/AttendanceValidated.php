<?php

namespace App\Modules\VieScolaire\Presences\Events;

use Core\Event;

class AttendanceValidated extends Event
{
    public function __construct(
        public readonly int    $appelId,
        public readonly int    $classeId,
        public readonly int    $enseignantId,
        public readonly string $dateAppel,
        public readonly int    $totalEleves,
        public readonly int    $totalPresents,
        public readonly int    $totalAbsents,
        public readonly int    $valideParId,
    ) {}
}

<?php

namespace App\Modules\VieScolaire\Presences\Events;

use Core\Event;

/** Fired when every student in the session has been pointed (fully complete). */
class AttendanceCompleted extends Event
{
    public function __construct(
        public readonly int    $appelId,
        public readonly int    $classeId,
        public readonly string $dateAppel,
        public readonly int    $totalEleves,
        public readonly int    $pointesCount,
    ) {}
}

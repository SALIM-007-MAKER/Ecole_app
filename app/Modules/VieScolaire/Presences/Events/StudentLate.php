<?php

namespace App\Modules\VieScolaire\Presences\Events;

use Core\Event;

/** Fired when a student is marked late (retard) in a session. */
class StudentLate extends Event
{
    public function __construct(
        public readonly int    $appelId,
        public readonly int    $eleveId,
        public readonly int    $classeId,
        public readonly string $dateAppel,
        public readonly ?int   $retardMinutes,   // null if not measured
        public readonly ?string $heureArrivee,
        public readonly int    $saisieParId,
    ) {}
}

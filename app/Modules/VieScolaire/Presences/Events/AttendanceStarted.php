<?php

namespace App\Modules\VieScolaire\Presences\Events;

use Core\Event;

class AttendanceStarted extends Event
{
    public function __construct(
        public readonly int    $appelId,
        public readonly int    $classeId,
        public readonly ?int   $matiereId,
        public readonly int    $enseignantId,
        public readonly string $dateAppel,
        public readonly string $typeAppel,    // 'journalier' | 'seance'
        public readonly string $anneeScolaire,
    ) {}
}

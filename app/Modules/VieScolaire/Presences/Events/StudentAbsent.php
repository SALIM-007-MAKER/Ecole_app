<?php

namespace App\Modules\VieScolaire\Presences\Events;

use Core\Event;

/**
 * Fired when a student is marked absent in a session.
 * absenceId: the ID of the vs_absences record automatically created.
 */
class StudentAbsent extends Event
{
    public function __construct(
        public readonly int    $appelId,
        public readonly int    $eleveId,
        public readonly int    $classeId,
        public readonly string $dateAppel,
        public readonly int    $absenceId,       // vs_absences.id (auto-created)
        public readonly int    $saisieParId,
    ) {}
}

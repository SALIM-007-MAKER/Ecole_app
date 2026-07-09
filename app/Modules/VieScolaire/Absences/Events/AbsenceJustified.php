<?php

namespace App\Modules\VieScolaire\Absences\Events;

use Core\Event;

class AbsenceJustified extends Event
{
    public function __construct(
        public readonly int    $absenceId,
        public readonly int    $justificationId,
        public readonly int    $eleveId,
        public readonly int    $classeId,
        public readonly int    $valideParId,
    ) {}
}

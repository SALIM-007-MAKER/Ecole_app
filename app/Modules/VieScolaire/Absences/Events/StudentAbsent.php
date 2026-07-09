<?php

namespace App\Modules\VieScolaire\Absences\Events;

use Core\Event;

class StudentAbsent extends Event
{
    public function __construct(
        public readonly int    $absenceId,
        public readonly int    $eleveId,
        public readonly int    $classeId,
        public readonly string $dateAbsence,
        public readonly string $type,          // 'absence' | 'retard' | 'dispense'
        public readonly int    $saisieParId,
    ) {}
}

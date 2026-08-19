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
    ) {
        parent::__construct();
    }

    public function toArray(): array
    {
        return [
            'absence_id'    => $this->absenceId,
            'eleve_id'      => $this->eleveId,
            'classe_id'     => $this->classeId,
            'date_absence'  => $this->dateAbsence,
            'type'          => $this->type,
            'saisie_par_id' => $this->saisieParId,
            'fired_at'      => $this->getFiredAt(),
        ];
    }
}

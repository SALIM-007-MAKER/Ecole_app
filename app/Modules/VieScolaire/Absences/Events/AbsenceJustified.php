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
    ) {
        parent::__construct();
    }

    public function toArray(): array
    {
        return [
            'absence_id'       => $this->absenceId,
            'justification_id' => $this->justificationId,
            'eleve_id'         => $this->eleveId,
            'classe_id'        => $this->classeId,
            'valide_par_id'    => $this->valideParId,
            'fired_at'         => $this->getFiredAt(),
        ];
    }
}

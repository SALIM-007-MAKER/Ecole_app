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
    ) {
        parent::__construct();
    }

    public function toArray(): array
    {
        return [
            'appel_id'       => $this->appelId,
            'classe_id'      => $this->classeId,
            'enseignant_id'  => $this->enseignantId,
            'date_appel'     => $this->dateAppel,
            'total_eleves'   => $this->totalEleves,
            'total_presents' => $this->totalPresents,
            'total_absents'  => $this->totalAbsents,
            'valide_par_id'  => $this->valideParId,
            'fired_at'       => $this->getFiredAt(),
        ];
    }
}

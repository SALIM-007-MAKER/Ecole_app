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
    ) {
        parent::__construct();
    }

    public function toArray(): array
    {
        return [
            'appel_id'      => $this->appelId,
            'eleve_id'      => $this->eleveId,
            'classe_id'     => $this->classeId,
            'date_appel'    => $this->dateAppel,
            'absence_id'    => $this->absenceId,
            'saisie_par_id' => $this->saisieParId,
            'fired_at'      => $this->getFiredAt(),
        ];
    }
}

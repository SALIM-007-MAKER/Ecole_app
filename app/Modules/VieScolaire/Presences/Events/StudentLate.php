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
    ) {
        parent::__construct();
    }

    public function toArray(): array
    {
        return [
            'appel_id'       => $this->appelId,
            'eleve_id'       => $this->eleveId,
            'classe_id'      => $this->classeId,
            'date_appel'     => $this->dateAppel,
            'retard_minutes' => $this->retardMinutes,
            'heure_arrivee'  => $this->heureArrivee,
            'saisie_par_id'  => $this->saisieParId,
            'fired_at'       => $this->getFiredAt(),
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\RH\Presences\Events;

use Core\Event;

class AttendanceLateDetected extends Event
{
    public function __construct(
        public readonly int    $presenceId,
        public readonly int    $employeId,
        public readonly string $datePresence,
        public readonly int    $retardMinutes,
        public readonly string $heureArrivee,
        public readonly string $heureReference,
        public readonly int    $createdBy
    ) {
        parent::__construct();
    }

    public function toArray(): array
    {
        return [
            'presence_id'    => $this->presenceId,
            'employe_id'     => $this->employeId,
            'date_presence'  => $this->datePresence,
            'retard_minutes' => $this->retardMinutes,
            'heure_arrivee'  => $this->heureArrivee,
            'heure_ref'      => $this->heureReference,
            'created_by'     => $this->createdBy,
            'fired_at'       => $this->firedAt(),
        ];
    }
}

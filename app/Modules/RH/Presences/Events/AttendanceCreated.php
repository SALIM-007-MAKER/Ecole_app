<?php

declare(strict_types=1);

namespace App\Modules\RH\Presences\Events;

use Core\Event;

class AttendanceCreated extends Event
{
    public function __construct(
        public readonly int    $presenceId,
        public readonly int    $employeId,
        public readonly string $datePresence,
        public readonly string $statut,
        public readonly string $modePointage,
        public readonly int    $createdBy
    ) {
        parent::__construct();
    }

    public function toArray(): array
    {
        return [
            'presence_id'   => $this->presenceId,
            'employe_id'    => $this->employeId,
            'date_presence' => $this->datePresence,
            'statut'        => $this->statut,
            'mode_pointage' => $this->modePointage,
            'created_by'    => $this->createdBy,
            'fired_at'      => $this->firedAt(),
        ];
    }
}

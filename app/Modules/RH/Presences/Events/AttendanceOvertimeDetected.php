<?php

declare(strict_types=1);

namespace App\Modules\RH\Presences\Events;

use Core\Event;

class AttendanceOvertimeDetected extends Event
{
    public function __construct(
        public readonly int    $presenceId,
        public readonly int    $employeId,
        public readonly string $datePresence,
        public readonly int    $heuresSuppMinutes,
        public readonly int    $dureeEffectiveMinutes,
        public readonly int    $dureeReferenceMinutes,
        public readonly int    $createdBy
    ) {}

    public function toArray(): array
    {
        return [
            'presence_id'             => $this->presenceId,
            'employe_id'              => $this->employeId,
            'date_presence'           => $this->datePresence,
            'heures_supp_minutes'     => $this->heuresSuppMinutes,
            'duree_effective_minutes' => $this->dureeEffectiveMinutes,
            'duree_reference_minutes' => $this->dureeReferenceMinutes,
            'created_by'              => $this->createdBy,
            'fired_at'                => $this->firedAt(),
        ];
    }
}

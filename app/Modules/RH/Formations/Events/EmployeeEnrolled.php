<?php

declare(strict_types=1);

namespace App\Modules\RH\Formations\Events;

use Core\Event;

class EmployeeEnrolled extends Event
{
    public function __construct(
        public readonly int    $inscriptionId,
        public readonly int    $sessionId,
        public readonly int    $employeId,
        public readonly string $formationTitre,
        public readonly int    $enrolledBy
    ) {}

    public function toArray(): array
    {
        return [
            'inscription_id'  => $this->inscriptionId,
            'session_id'      => $this->sessionId,
            'employe_id'      => $this->employeId,
            'formation_titre' => $this->formationTitre,
            'enrolled_by'     => $this->enrolledBy,
            'fired_at'        => $this->firedAt(),
        ];
    }
}

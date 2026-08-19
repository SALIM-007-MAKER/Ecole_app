<?php

declare(strict_types=1);

namespace App\Modules\RH\Organisation\Events;

use Core\Event;

class PositionAssigned extends Event
{
    public function __construct(
        public readonly int    $employe_id,
        public readonly int    $posteId,
        public readonly string $posteIntitule,
        public readonly int    $assignedBy
    ) {
        parent::__construct();
    }

    public function toArray(): array
    {
        return [
            'employe_id'     => $this->employe_id,
            'poste_id'       => $this->posteId,
            'poste_intitule' => $this->posteIntitule,
            'assigned_by'    => $this->assignedBy,
            'fired_at'       => $this->firedAt(),
        ];
    }
}

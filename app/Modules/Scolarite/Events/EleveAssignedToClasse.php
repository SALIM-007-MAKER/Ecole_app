<?php

namespace App\Modules\Scolarite\Events;

use Core\Event;

class EleveAssignedToClasse extends Event
{
    public function __construct(
        public readonly int $eleveId,
        public readonly int $classeId,
        public readonly int $assignedById,
    ) {
        parent::__construct();
    }

    public function toArray(): array
    {
        return [
            'eleve_id'      => $this->eleveId,
            'classe_id'     => $this->classeId,
            'assigned_by_id'=> $this->assignedById,
            'fired_at'      => $this->getFiredAt(),
        ];
    }
}

<?php

namespace App\Modules\Scolarite\Events;

use Core\Event;

class MatiereUpdated extends Event
{
    public function __construct(
        public readonly int   $matiereId,
        public readonly int   $updatedById,
        public readonly array $changedFields = [],
    ) {}

    public function getName(): string { return 'matiere.updated'; }

    public function toArray(): array
    {
        return [
            'matiere_id'    => $this->matiereId,
            'updated_by'    => $this->updatedById,
            'changed_fields'=> $this->changedFields,
            'fired_at'      => $this->getFiredAt(),
        ];
    }
}

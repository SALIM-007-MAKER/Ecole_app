<?php

namespace App\Modules\Scolarite\Events;

use Core\Event;

class ParentUpdated extends Event
{
    public function __construct(
        public readonly int   $familleId,
        public readonly int   $updatedById,
        public readonly array $changedFields = [],
    ) {}

    public function getName(): string { return 'famille.updated'; }

    public function toArray(): array
    {
        return [
            'famille_id'    => $this->familleId,
            'updated_by'    => $this->updatedById,
            'changed_fields'=> $this->changedFields,
            'fired_at'      => $this->getFiredAt(),
        ];
    }
}

<?php

namespace App\Modules\Scolarite\Events;

use Core\Event;

class ClasseUpdated extends Event
{
    public function __construct(
        public readonly int   $classeId,
        public readonly int   $updatedById,
        public readonly array $changedFields = [],
    ) {
        parent::__construct();
    }

    public function toArray(): array
    {
        return [
            'classe_id'      => $this->classeId,
            'updated_by_id'  => $this->updatedById,
            'changed_fields' => $this->changedFields,
            'fired_at'       => $this->getFiredAt(),
        ];
    }
}

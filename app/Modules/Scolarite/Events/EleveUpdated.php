<?php

namespace App\Modules\Scolarite\Events;

use Core\Event;

class EleveUpdated extends Event
{
    public function __construct(
        public readonly int   $eleveId,
        public readonly int   $updatedById,
        public readonly array $changedFields = [],
    ) {
        parent::__construct();
    }

    public function toArray(): array
    {
        return [
            'eleve_id'       => $this->eleveId,
            'updated_by_id'  => $this->updatedById,
            'changed_fields' => $this->changedFields,
        ];
    }
}

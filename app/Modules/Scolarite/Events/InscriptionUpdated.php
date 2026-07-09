<?php

namespace App\Modules\Scolarite\Events;

use Core\Event;

class InscriptionUpdated extends Event
{
    public function __construct(
        public readonly int   $inscriptionId,
        public readonly int   $updatedById,
        public readonly array $changedFields = [],
    ) {
        parent::__construct();
    }

    public function toArray(): array
    {
        return [
            'inscription_id' => $this->inscriptionId,
            'updated_by_id'  => $this->updatedById,
            'changed_fields' => $this->changedFields,
            'fired_at'       => $this->getFiredAt(),
        ];
    }
}

<?php

namespace App\Modules\Finance\Events;

use Core\Event;

class FeeUpdated extends Event
{
    public function __construct(
        public readonly int    $fraisTypeId,
        public readonly string $nom,
        public readonly array  $changedFields,
        public readonly int    $updatedById,
    ) {
        parent::__construct();
    }

    public function getName(): string
    {
        return 'finance.fee.updated';
    }

    public function toArray(): array
    {
        return [
            'frais_type_id' => $this->fraisTypeId,
            'nom'           => $this->nom,
            'changed'       => $this->changedFields,
            'updated_by_id' => $this->updatedById,
            'fired_at'      => $this->getFiredAt(),
        ];
    }
}

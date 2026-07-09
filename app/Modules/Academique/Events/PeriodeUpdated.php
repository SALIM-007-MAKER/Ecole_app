<?php

namespace App\Modules\Academique\Events;

use Core\Event;

class PeriodeUpdated extends Event
{
    public function __construct(
        public readonly int    $periodeId,
        public readonly int    $updatedById,
        public readonly array  $changedFields,
    ) {
        parent::__construct();
    }

    public function getName(): string
    {
        return 'academique.periode.updated';
    }

    public function toArray(): array
    {
        return [
            'periode_id'    => $this->periodeId,
            'updated_by_id' => $this->updatedById,
            'changed_fields'=> $this->changedFields,
            'fired_at'      => $this->getFiredAt(),
        ];
    }
}

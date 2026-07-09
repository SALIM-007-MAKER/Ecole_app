<?php

namespace App\Modules\Finance\Events;

use Core\Event;

class FeeArchived extends Event
{
    public function __construct(
        public readonly int    $fraisTypeId,
        public readonly string $nom,
        public readonly string $motif,
        public readonly int    $archivedById,
    ) {
        parent::__construct();
    }

    public function getName(): string
    {
        return 'finance.fee.archived';
    }

    public function toArray(): array
    {
        return [
            'frais_type_id' => $this->fraisTypeId,
            'nom'           => $this->nom,
            'motif'         => $this->motif,
            'archived_by_id'=> $this->archivedById,
            'fired_at'      => $this->getFiredAt(),
        ];
    }
}

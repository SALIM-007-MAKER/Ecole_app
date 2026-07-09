<?php

namespace App\Modules\Finance\Events;

use Core\Event;

class FeeDeactivated extends Event
{
    public function __construct(
        public readonly int    $fraisTypeId,
        public readonly string $nom,
        public readonly int    $deactivatedById,
    ) {
        parent::__construct();
    }

    public function getName(): string
    {
        return 'finance.fee.deactivated';
    }

    public function toArray(): array
    {
        return [
            'frais_type_id'    => $this->fraisTypeId,
            'nom'              => $this->nom,
            'deactivated_by_id'=> $this->deactivatedById,
            'fired_at'         => $this->getFiredAt(),
        ];
    }
}

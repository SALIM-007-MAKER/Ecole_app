<?php

namespace App\Modules\Finance\Events;

use Core\Event;

class FeeActivated extends Event
{
    public function __construct(
        public readonly int    $fraisTypeId,
        public readonly string $nom,
        public readonly int    $activatedById,
    ) {
        parent::__construct();
    }

    public function getName(): string
    {
        return 'finance.fee.activated';
    }

    public function toArray(): array
    {
        return [
            'frais_type_id'  => $this->fraisTypeId,
            'nom'            => $this->nom,
            'activated_by_id'=> $this->activatedById,
            'fired_at'       => $this->getFiredAt(),
        ];
    }
}

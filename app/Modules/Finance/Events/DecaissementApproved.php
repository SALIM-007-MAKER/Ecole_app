<?php

namespace App\Modules\Finance\Events;

use Core\Event;

class DecaissementApproved extends Event
{
    public function __construct(
        public readonly int    $decaissementId,
        public readonly string $numero,
        public readonly float  $montant,
        public readonly int    $approuveParId,
    ) {
        parent::__construct();
    }

    public function getName(): string
    {
        return 'finance.decaissement.approved';
    }

    public function toArray(): array
    {
        return [
            'decaissement_id' => $this->decaissementId,
            'numero'          => $this->numero,
            'montant'         => $this->montant,
            'approuve_par_id' => $this->approuveParId,
            'fired_at'        => $this->getFiredAt(),
        ];
    }
}

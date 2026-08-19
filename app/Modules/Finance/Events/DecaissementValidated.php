<?php

namespace App\Modules\Finance\Events;

use Core\Event;

class DecaissementValidated extends Event
{
    public function __construct(
        public readonly int    $decaissementId,
        public readonly string $numero,
        public readonly float  $montant,
        public readonly int    $valideParId,
    ) {
        parent::__construct();
    }

    public function getName(): string
    {
        return 'finance.decaissement.validated';
    }

    public function toArray(): array
    {
        return [
            'decaissement_id' => $this->decaissementId,
            'numero'          => $this->numero,
            'montant'         => $this->montant,
            'valide_par_id'   => $this->valideParId,
            'fired_at'        => $this->getFiredAt(),
        ];
    }
}

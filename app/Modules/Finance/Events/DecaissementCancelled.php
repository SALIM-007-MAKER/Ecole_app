<?php

namespace App\Modules\Finance\Events;

use Core\Event;

class DecaissementCancelled extends Event
{
    public function __construct(
        public readonly int    $decaissementId,
        public readonly string $numero,
        public readonly string $ancienStatut,
        public readonly string $motif,
        public readonly int    $annuleParId,
    ) {
        parent::__construct();
    }

    public function getName(): string
    {
        return 'finance.decaissement.cancelled';
    }

    public function toArray(): array
    {
        return [
            'decaissement_id' => $this->decaissementId,
            'numero'          => $this->numero,
            'ancien_statut'   => $this->ancienStatut,
            'motif'           => $this->motif,
            'annule_par_id'   => $this->annuleParId,
            'fired_at'        => $this->getFiredAt(),
        ];
    }
}

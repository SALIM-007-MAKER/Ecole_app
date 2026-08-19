<?php

namespace App\Modules\Finance\Events;

use Core\Event;

class DecaissementRejected extends Event
{
    public function __construct(
        public readonly int    $decaissementId,
        public readonly string $numero,
        public readonly string $motif,
        public readonly int    $rejeteParId,
    ) {
        parent::__construct();
    }

    public function getName(): string
    {
        return 'finance.decaissement.rejected';
    }

    public function toArray(): array
    {
        return [
            'decaissement_id' => $this->decaissementId,
            'numero'          => $this->numero,
            'motif'           => $this->motif,
            'rejete_par_id'   => $this->rejeteParId,
            'fired_at'        => $this->getFiredAt(),
        ];
    }
}

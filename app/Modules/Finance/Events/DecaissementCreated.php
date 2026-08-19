<?php

namespace App\Modules\Finance\Events;

use Core\Event;

class DecaissementCreated extends Event
{
    public function __construct(
        public readonly int    $decaissementId,
        public readonly string $numero,
        public readonly string $libelle,
        public readonly float  $montant,
        public readonly int    $saisiParId,
    ) {
        parent::__construct();
    }

    public function getName(): string
    {
        return 'finance.decaissement.created';
    }

    public function toArray(): array
    {
        return [
            'decaissement_id' => $this->decaissementId,
            'numero'          => $this->numero,
            'libelle'         => $this->libelle,
            'montant'         => $this->montant,
            'saisi_par_id'    => $this->saisiParId,
            'fired_at'        => $this->getFiredAt(),
        ];
    }
}

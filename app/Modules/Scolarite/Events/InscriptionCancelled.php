<?php

namespace App\Modules\Scolarite\Events;

use Core\Event;

class InscriptionCancelled extends Event
{
    public function __construct(
        public readonly int    $inscriptionId,
        public readonly int    $eleveId,
        public readonly int    $cancelledById,
        public readonly string $motif = '',
    ) {
        parent::__construct();
    }

    public function toArray(): array
    {
        return [
            'inscription_id' => $this->inscriptionId,
            'eleve_id'       => $this->eleveId,
            'cancelled_by_id'=> $this->cancelledById,
            'motif'          => $this->motif,
            'fired_at'       => $this->getFiredAt(),
        ];
    }
}

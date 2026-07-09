<?php

namespace App\Modules\Academique\Events;

use Core\Event;

class PeriodeActivated extends Event
{
    public function __construct(
        public readonly int    $periodeId,
        public readonly string $nom,
        public readonly string $anneeScolaire,
        public readonly int    $activatedById,
    ) {
        parent::__construct();
    }

    public function getName(): string
    {
        return 'academique.periode.activated';
    }

    public function toArray(): array
    {
        return [
            'periode_id'      => $this->periodeId,
            'nom'             => $this->nom,
            'annee_scolaire'  => $this->anneeScolaire,
            'activated_by_id' => $this->activatedById,
            'fired_at'        => $this->getFiredAt(),
        ];
    }
}

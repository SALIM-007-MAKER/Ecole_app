<?php

namespace App\Modules\Academique\Events;

use Core\Event;

class PeriodeCreated extends Event
{
    public function __construct(
        public readonly int    $periodeId,
        public readonly string $nom,
        public readonly string $anneeScolaire,
        public readonly string $typePeriode,
        public readonly int    $numero,
        public readonly int    $createdById,
    ) {
        parent::__construct();
    }

    public function getName(): string
    {
        return 'academique.periode.created';
    }

    public function toArray(): array
    {
        return [
            'periode_id'     => $this->periodeId,
            'nom'            => $this->nom,
            'annee_scolaire' => $this->anneeScolaire,
            'type_periode'   => $this->typePeriode,
            'numero'         => $this->numero,
            'created_by_id'  => $this->createdById,
            'fired_at'       => $this->getFiredAt(),
        ];
    }
}

<?php

namespace App\Modules\Scolarite\Events;

use Core\Event;

class InscriptionCreated extends Event
{
    public function __construct(
        public readonly int    $inscriptionId,
        public readonly int    $eleveId,
        public readonly string $anneeScolaire,
        public readonly ?int   $classeId,
        public readonly int    $createdById,
    ) {
        parent::__construct();
    }

    public function toArray(): array
    {
        return [
            'inscription_id' => $this->inscriptionId,
            'eleve_id'       => $this->eleveId,
            'annee_scolaire' => $this->anneeScolaire,
            'classe_id'      => $this->classeId,
            'created_by_id'  => $this->createdById,
            'fired_at'       => $this->getFiredAt(),
        ];
    }
}

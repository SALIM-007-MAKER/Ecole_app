<?php

namespace App\Modules\Scolarite\Events;

use Core\Event;

class ClasseCreated extends Event
{
    public function __construct(
        public readonly int    $classeId,
        public readonly string $nom,
        public readonly string $niveau,
        public readonly string $anneeScolaire,
        public readonly int    $createdById,
    ) {
        parent::__construct();
    }

    public function toArray(): array
    {
        return [
            'classe_id'      => $this->classeId,
            'nom'            => $this->nom,
            'niveau'         => $this->niveau,
            'annee_scolaire' => $this->anneeScolaire,
            'created_by_id'  => $this->createdById,
            'fired_at'       => $this->getFiredAt(),
        ];
    }
}

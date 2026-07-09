<?php

namespace App\Modules\Scolarite\Events;

use Core\Event;

class MatiereCreated extends Event
{
    public function __construct(
        public readonly int    $matiereId,
        public readonly string $nom,
        public readonly float  $coefficient,
        public readonly int    $createdById,
    ) {}

    public function getName(): string { return 'matiere.created'; }

    public function toArray(): array
    {
        return [
            'matiere_id'  => $this->matiereId,
            'nom'         => $this->nom,
            'coefficient' => $this->coefficient,
            'created_by'  => $this->createdById,
            'fired_at'    => $this->getFiredAt(),
        ];
    }
}

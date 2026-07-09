<?php

namespace App\Modules\Scolarite\Events;

use Core\Event;

class ParentCreated extends Event
{
    public function __construct(
        public readonly int    $familleId,
        public readonly string $nom,
        public readonly int    $createdById,
    ) {}

    public function getName(): string { return 'famille.created'; }

    public function toArray(): array
    {
        return [
            'famille_id'   => $this->familleId,
            'nom'          => $this->nom,
            'created_by'   => $this->createdById,
            'fired_at'     => $this->getFiredAt(),
        ];
    }
}

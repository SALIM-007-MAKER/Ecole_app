<?php

namespace App\Modules\Scolarite\Events;

use Core\Event;

class ParentUnlinkedFromStudent extends Event
{
    public function __construct(
        public readonly int $familleId,
        public readonly int $eleveId,
        public readonly int $unlinkedById,
    ) {}

    public function getName(): string { return 'famille.unlinked_from_student'; }

    public function toArray(): array
    {
        return [
            'famille_id'  => $this->familleId,
            'eleve_id'    => $this->eleveId,
            'unlinked_by' => $this->unlinkedById,
            'fired_at'    => $this->getFiredAt(),
        ];
    }
}

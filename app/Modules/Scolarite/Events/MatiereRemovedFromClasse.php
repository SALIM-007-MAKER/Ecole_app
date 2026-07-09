<?php

namespace App\Modules\Scolarite\Events;

use Core\Event;

class MatiereRemovedFromClasse extends Event
{
    public function __construct(
        public readonly int $matiereId,
        public readonly int $classeId,
        public readonly int $removedById,
    ) {}

    public function getName(): string { return 'matiere.removed_from_classe'; }

    public function toArray(): array
    {
        return [
            'matiere_id' => $this->matiereId,
            'classe_id'  => $this->classeId,
            'removed_by' => $this->removedById,
            'fired_at'   => $this->getFiredAt(),
        ];
    }
}

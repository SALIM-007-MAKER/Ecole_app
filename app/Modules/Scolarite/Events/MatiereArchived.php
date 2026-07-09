<?php

namespace App\Modules\Scolarite\Events;

use Core\Event;

class MatiereArchived extends Event
{
    public function __construct(
        public readonly int    $matiereId,
        public readonly string $nom,
        public readonly int    $archivedById,
    ) {}

    public function getName(): string { return 'matiere.archived'; }

    public function toArray(): array
    {
        return [
            'matiere_id'  => $this->matiereId,
            'nom'         => $this->nom,
            'archived_by' => $this->archivedById,
            'fired_at'    => $this->getFiredAt(),
        ];
    }
}

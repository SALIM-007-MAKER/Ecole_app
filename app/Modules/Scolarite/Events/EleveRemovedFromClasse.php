<?php

namespace App\Modules\Scolarite\Events;

use Core\Event;

class EleveRemovedFromClasse extends Event
{
    public function __construct(
        public readonly int $eleveId,
        public readonly int $classeId,
        public readonly int $removedById,
    ) {
        parent::__construct();
    }

    public function toArray(): array
    {
        return [
            'eleve_id'      => $this->eleveId,
            'classe_id'     => $this->classeId,
            'removed_by_id' => $this->removedById,
            'fired_at'      => $this->getFiredAt(),
        ];
    }
}

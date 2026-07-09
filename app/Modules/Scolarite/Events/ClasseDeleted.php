<?php

namespace App\Modules\Scolarite\Events;

use Core\Event;

class ClasseDeleted extends Event
{
    public function __construct(
        public readonly int    $classeId,
        public readonly string $nom,
        public readonly int    $deletedById,
    ) {
        parent::__construct();
    }

    public function toArray(): array
    {
        return [
            'classe_id'     => $this->classeId,
            'nom'           => $this->nom,
            'deleted_by_id' => $this->deletedById,
            'fired_at'      => $this->getFiredAt(),
        ];
    }
}

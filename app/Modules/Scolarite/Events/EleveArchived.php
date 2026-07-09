<?php

namespace App\Modules\Scolarite\Events;

use Core\Event;

class EleveArchived extends Event
{
    public function __construct(
        public readonly int    $eleveId,
        public readonly int    $archivedById,
        public readonly string $motif = '',
    ) {
        parent::__construct();
    }

    public function toArray(): array
    {
        return [
            'eleve_id'      => $this->eleveId,
            'archived_by_id' => $this->archivedById,
            'motif'         => $this->motif,
        ];
    }
}

<?php

namespace App\Events;

use Core\Event;

class ControleUpdated extends Event
{
    public function __construct(
        public readonly int   $controleId,
        public readonly int   $updatedById,
        public readonly int   $classeId,
        public readonly int   $periodeId,
        public readonly int   $matiereId,
        public readonly array $changedFields = [],
    ) {
        parent::__construct();
    }

    public function toArray(): array
    {
        return [
            'controle_id'   => $this->controleId,
            'classe_id'     => $this->classeId,
            'periode_id'    => $this->periodeId,
            'matiere_id'    => $this->matiereId,
            'changed_fields'=> $this->changedFields,
        ];
    }
}

<?php

namespace App\Modules\Academique\Events;

use Core\Event;

class NoteUpdated extends Event
{
    public function __construct(
        public readonly int    $noteId,
        public readonly int    $evaluationId,
        public readonly int    $eleveId,
        public readonly ?float $oldValeur,
        public readonly ?float $newValeur,
        public readonly int    $updatedById,
    ) {
        parent::__construct();
    }

    public function getName(): string
    {
        return 'note.updated';
    }

    public function toArray(): array
    {
        return [
            'note_id'        => $this->noteId,
            'evaluation_id'  => $this->evaluationId,
            'eleve_id'       => $this->eleveId,
            'old_valeur'     => $this->oldValeur,
            'new_valeur'     => $this->newValeur,
            'updated_by_id'  => $this->updatedById,
            'fired_at'       => $this->getFiredAt(),
        ];
    }
}

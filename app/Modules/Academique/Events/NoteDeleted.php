<?php

namespace App\Modules\Academique\Events;

use Core\Event;

class NoteDeleted extends Event
{
    public function __construct(
        public readonly int    $noteId,
        public readonly int    $evaluationId,
        public readonly int    $eleveId,
        public readonly ?float $valeur,
        public readonly int    $deletedById,
    ) {
        parent::__construct();
    }

    public function getName(): string
    {
        return 'note.deleted';
    }

    public function toArray(): array
    {
        return [
            'note_id'       => $this->noteId,
            'evaluation_id' => $this->evaluationId,
            'eleve_id'      => $this->eleveId,
            'valeur'        => $this->valeur,
            'deleted_by_id' => $this->deletedById,
            'fired_at'      => $this->getFiredAt(),
        ];
    }
}

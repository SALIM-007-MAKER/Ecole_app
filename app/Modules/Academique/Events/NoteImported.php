<?php

namespace App\Modules\Academique\Events;

use Core\Event;

class NoteImported extends Event
{
    public function __construct(
        public readonly int    $evaluationId,
        public readonly int    $created,
        public readonly int    $updated,
        public readonly int    $errors,
        public readonly int    $importedById,
    ) {
        parent::__construct();
    }

    public function getName(): string
    {
        return 'note.imported';
    }

    public function toArray(): array
    {
        return [
            'evaluation_id'  => $this->evaluationId,
            'created'        => $this->created,
            'updated'        => $this->updated,
            'errors'         => $this->errors,
            'imported_by_id' => $this->importedById,
            'fired_at'       => $this->getFiredAt(),
        ];
    }
}

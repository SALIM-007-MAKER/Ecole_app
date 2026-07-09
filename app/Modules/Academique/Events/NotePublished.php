<?php

namespace App\Modules\Academique\Events;

use Core\Event;

class NotePublished extends Event
{
    public function __construct(
        public readonly int $evaluationId,
        public readonly int $count,
        public readonly int $publishedById,
    ) {
        parent::__construct();
    }

    public function getName(): string
    {
        return 'note.published';
    }

    public function toArray(): array
    {
        return [
            'evaluation_id'   => $this->evaluationId,
            'count'           => $this->count,
            'published_by_id' => $this->publishedById,
            'fired_at'        => $this->getFiredAt(),
        ];
    }
}

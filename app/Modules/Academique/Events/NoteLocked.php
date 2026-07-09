<?php

namespace App\Modules\Academique\Events;

use Core\Event;

class NoteLocked extends Event
{
    public function __construct(
        public readonly int $evaluationId,
        public readonly int $count,
        public readonly int $lockedById,
    ) {
        parent::__construct();
    }

    public function getName(): string
    {
        return 'note.locked';
    }

    public function toArray(): array
    {
        return [
            'evaluation_id' => $this->evaluationId,
            'count'         => $this->count,
            'locked_by_id'  => $this->lockedById,
            'fired_at'      => $this->getFiredAt(),
        ];
    }
}

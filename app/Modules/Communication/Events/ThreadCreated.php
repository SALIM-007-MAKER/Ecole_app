<?php

declare(strict_types=1);

namespace App\Modules\Communication\Events;

use Core\Event;

class ThreadCreated extends Event
{
    public function __construct(
        public readonly int   $threadId,
        public readonly int   $createdById,
        public readonly array $participantIds,
    ) {
        parent::__construct();
    }

    public function toArray(): array
    {
        return [
            'thread_id'       => $this->threadId,
            'created_by_id'   => $this->createdById,
            'participant_ids' => $this->participantIds,
        ];
    }
}

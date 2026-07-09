<?php

declare(strict_types=1);

namespace App\Modules\Communication\Events;

use Core\Event;

class ThreadMessageSent extends Event
{
    public function __construct(
        public readonly int   $threadId,
        public readonly int   $messageId,
        public readonly int   $senderId,
        public readonly array $participantIds,
    ) {
        parent::__construct();
    }

    public function toArray(): array
    {
        return [
            'thread_id'       => $this->threadId,
            'message_id'      => $this->messageId,
            'sender_id'       => $this->senderId,
            'participant_ids' => $this->participantIds,
        ];
    }
}

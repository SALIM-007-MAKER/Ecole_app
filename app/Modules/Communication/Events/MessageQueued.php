<?php

declare(strict_types=1);

namespace App\Modules\Communication\Events;

use Core\Event;

class MessageQueued extends Event
{
    public function __construct(
        public readonly int     $queueId,
        public readonly string  $canal,
        public readonly string  $type,
        public readonly ?int    $userId,
    ) {
        parent::__construct();
    }

    public function toArray(): array
    {
        return [
            'queue_id' => $this->queueId,
            'canal'    => $this->canal,
            'type'     => $this->type,
            'user_id'  => $this->userId,
        ];
    }
}

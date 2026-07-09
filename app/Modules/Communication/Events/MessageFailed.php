<?php

declare(strict_types=1);

namespace App\Modules\Communication\Events;

use Core\Event;

class MessageFailed extends Event
{
    public function __construct(
        public readonly int    $queueId,
        public readonly string $canal,
        public readonly string $type,
        public readonly int    $userId,
        public readonly string $raison,
        public readonly int    $tentative,
    ) {
        parent::__construct();
    }

    public function toArray(): array
    {
        return [
            'queue_id'  => $this->queueId,
            'canal'     => $this->canal,
            'type'      => $this->type,
            'user_id'   => $this->userId,
            'raison'    => $this->raison,
            'tentative' => $this->tentative,
        ];
    }
}

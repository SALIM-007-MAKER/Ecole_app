<?php

declare(strict_types=1);

namespace App\Modules\Communication\Events;

use Core\Event;

class NotificationsBulkRead extends Event
{
    public function __construct(
        public readonly int $userId,
        public readonly int $count,
    ) {
        parent::__construct();
    }

    public function toArray(): array
    {
        return [
            'user_id' => $this->userId,
            'count'   => $this->count,
        ];
    }
}

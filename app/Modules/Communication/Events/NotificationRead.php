<?php

declare(strict_types=1);

namespace App\Modules\Communication\Events;

use Core\Event;

class NotificationRead extends Event
{
    public function __construct(
        public readonly int $notificationId,
        public readonly int $userId,
    ) {
        parent::__construct();
    }

    public function toArray(): array
    {
        return [
            'notification_id' => $this->notificationId,
            'user_id'         => $this->userId,
        ];
    }
}

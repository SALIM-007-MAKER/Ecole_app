<?php

declare(strict_types=1);

namespace App\Modules\Communication\Events;

use Core\Event;

class NotificationCreated extends Event
{
    public function __construct(
        public readonly int    $notificationId,
        public readonly int    $userId,
        public readonly string $type,
        public readonly string $titre,
        public readonly string $moduleSource,
    ) {
        parent::__construct();
    }

    public function toArray(): array
    {
        return [
            'notification_id' => $this->notificationId,
            'user_id'         => $this->userId,
            'type'            => $this->type,
            'titre'           => $this->titre,
            'module_source'   => $this->moduleSource,
        ];
    }
}

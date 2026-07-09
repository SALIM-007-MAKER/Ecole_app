<?php

declare(strict_types=1);

namespace App\Modules\Communication\Events;

use Core\Event;

class PreferencesUpdated extends Event
{
    public function __construct(
        public readonly int    $userId,
        public readonly string $typeNotification,
        public readonly array  $changes,
    ) {
        parent::__construct();
    }

    public function toArray(): array
    {
        return [
            'user_id'           => $this->userId,
            'type_notification' => $this->typeNotification,
            'changes'           => $this->changes,
        ];
    }
}

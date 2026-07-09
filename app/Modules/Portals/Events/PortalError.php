<?php
declare(strict_types=1);

namespace App\Modules\Portals\Events;

use Core\Event;

class PortalError extends Event
{
    public function __construct(
        public readonly string    $portal,
        public readonly int       $userId,
        public readonly int       $etablissementId,
        public readonly string    $context,
        public readonly string    $message,
        public readonly ?\Throwable $exception = null,
    ) {}
}

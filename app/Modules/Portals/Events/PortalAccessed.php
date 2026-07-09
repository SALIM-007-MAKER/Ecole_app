<?php
declare(strict_types=1);

namespace App\Modules\Portals\Events;

use Core\Event;

class PortalAccessed extends Event
{
    public function __construct(
        public readonly string $portal,
        public readonly int    $userId,
        public readonly int    $etablissementId,
        public readonly string $ip,
        public readonly string $page = '',
    ) {}
}

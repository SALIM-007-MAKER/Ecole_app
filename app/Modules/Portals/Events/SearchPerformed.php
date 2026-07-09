<?php
declare(strict_types=1);

namespace App\Modules\Portals\Events;

use Core\Event;

class SearchPerformed extends Event
{
    public function __construct(
        public readonly string $query,
        public readonly string $portal,
        public readonly int    $userId,
        public readonly int    $etablissementId,
        public readonly int    $resultCount,
        public readonly int    $timeMs,
    ) {}
}

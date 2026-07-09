<?php
declare(strict_types=1);

namespace App\Modules\Portals\Events;

use Core\Event;

class WidgetRefreshed extends Event
{
    public function __construct(
        public readonly string $widgetId,
        public readonly string $portal,
        public readonly int    $userId,
        public readonly int    $etablissementId,
    ) {}
}

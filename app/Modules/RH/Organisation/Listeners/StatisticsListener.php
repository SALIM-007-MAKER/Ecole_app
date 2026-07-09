<?php

declare(strict_types=1);

namespace App\Modules\RH\Organisation\Listeners;

use Core\Event;
use Core\Listener;

class StatisticsListener implements Listener
{
    public function handle(Event $event): void
    {
        // Stub — Phase 6.8 : recalcul taux d'occupation postes, dashboard RH
    }
}

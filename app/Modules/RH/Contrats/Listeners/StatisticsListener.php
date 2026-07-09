<?php

declare(strict_types=1);

namespace App\Modules\RH\Contrats\Listeners;

use Core\Event;
use Core\Listener;

class StatisticsListener implements Listener
{
    public function handle(Event $event): void
    {
        // Phase 6.8 — Dashboard RH :
        // recalcul taux de CDI/CDD, durée moyenne, suivi taux d'encadrement
        // Phase 6.12 — Paie : hook masse salariale contractuelle
    }
}

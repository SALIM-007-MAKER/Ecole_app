<?php

declare(strict_types=1);

namespace App\Modules\RH\Conges\Listeners;

use Core\Event;
use Core\Listener;

/**
 * Stub Phase 6.10 / 6.12.
 * Objectifs :
 *   - Mettre à jour le cache des indicateurs RH (taux d'absentéisme, etc.)
 *   - Alimenter le moteur de paie (Phase 6.12) avec les absences payées/non payées
 */
class StatisticsListener implements Listener
{
    public function handle(Event $event): void
    {
        // Stub — implémentation Phase 6.10/6.12
    }
}

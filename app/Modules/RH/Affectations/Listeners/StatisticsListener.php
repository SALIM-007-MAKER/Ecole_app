<?php

declare(strict_types=1);

namespace App\Modules\RH\Affectations\Listeners;

use Core\Listener;
use Core\Event;

/**
 * Stub — agrégats activés en Phase 6.10 (Dashboard RH).
 *
 * Hooks prévus :
 *  - AssignmentCreated     → incrémenter nb_affectations_actives par département
 *  - AssignmentTransferred → mettre à jour répartition par poste/département
 *  - AssignmentArchived    → décrémenter compteurs
 */
class StatisticsListener implements Listener
{
    public function handle(Event $event): void
    {
        // Phase 6.10 : mise à jour des KPIs dashboard RH
    }
}

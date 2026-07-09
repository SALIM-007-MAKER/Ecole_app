<?php

declare(strict_types=1);

namespace App\Modules\Inventaire\Listeners;

use Core\Event;
use Core\Listener;
use App\Modules\Inventaire\Events\StockAlerte;
use App\Modules\Inventaire\Events\MaintenanceCreee;
use App\Modules\Inventaire\Events\InventaireTermine;
use App\Modules\Inventaire\Events\AffectationCreee;

/**
 * Intégration Module Communication V2.
 * StockAlerte → notification responsable stock.
 * MaintenanceCreee → notification responsable maintenance.
 * InventaireTermine → rapport de clôture.
 * Stub V2.
 */
class InvCommunicationListener implements Listener
{
    public function handle(Event $event): void
    {
        $data = $event->toArray();

        if ($event instanceof StockAlerte) {
            error_log("[InvComm] StockAlerte article#{$data['article_id']} type={$data['type']} valeur={$data['valeur_actuelle']}");
            return;
        }

        if ($event instanceof MaintenanceCreee) {
            error_log("[InvComm] MaintenanceCreee #{$data['maintenance_id']} article#{$data['article_id']} le {$data['date_planifiee']}");
            return;
        }

        if ($event instanceof InventaireTermine) {
            error_log("[InvComm] InventaireTermine #{$data['inventaire_id']} — {$data['nb_ecarts']} écarts");
            return;
        }

        if ($event instanceof AffectationCreee) {
            error_log("[InvComm] AffectationCreee #{$data['affectation_id']} → user#{$data['user_id']}");
        }
    }
}

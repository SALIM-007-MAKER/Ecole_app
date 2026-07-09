<?php

declare(strict_types=1);

namespace App\Modules\Inventaire\Listeners;

use Core\Event;
use Core\Listener;
use App\Modules\Inventaire\Events\CommandeValidee;
use App\Modules\Inventaire\Events\MaintenanceTerminee;
use App\Modules\Inventaire\Events\InventaireTermine;

/**
 * Intégration Module Documents V2.
 * CommandeValidee → archiver bon de commande PDF.
 * MaintenanceTerminee → archiver rapport de maintenance.
 * InventaireTermine → archiver rapport inventaire.
 * Stub V2.
 */
class InvDocumentsIntegrationListener implements Listener
{
    public function handle(Event $event): void
    {
        $data = $event->toArray();

        if ($event instanceof CommandeValidee) {
            error_log("[InvDocs] CommandeValidee #{$data['commande_id']} — archivage bon commande prévu V3");
            return;
        }

        if ($event instanceof MaintenanceTerminee) {
            error_log("[InvDocs] MaintenanceTerminee #{$data['maintenance_id']} — archivage rapport prévu V3");
            return;
        }

        if ($event instanceof InventaireTermine) {
            error_log("[InvDocs] InventaireTermine #{$data['inventaire_id']} — archivage rapport prévu V3");
        }
    }
}

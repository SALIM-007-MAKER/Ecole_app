<?php
declare(strict_types=1);

namespace App\Modules\Portals\Listeners;

use App\Modules\Portals\Events\DashboardViewed;
use App\Modules\Portals\Events\SearchPerformed;
use App\Modules\Portals\Events\WidgetRefreshed;
use Core\Database;
use Core\Event;
use Core\Listener;

class PortalAnalyticsListener implements Listener
{
    public function handle(Event $event): void
    {
        if ($event instanceof DashboardViewed) {
            $this->onDashboardViewed($event);
            return;
        }

        if ($event instanceof SearchPerformed) {
            $this->onSearchPerformed($event);
            return;
        }

        if ($event instanceof WidgetRefreshed) {
            $this->onWidgetRefreshed($event);
            return;
        }
    }

    public function onDashboardViewed(DashboardViewed $event): void
    {
        // Incrément compteur d'accès portail (peut alimenter des analytics futurs)
        try {
            $pdo  = Database::getInstance()->getConnection();
            $stmt = $pdo->prepare(
                'UPDATE portal_access_logs
                 SET action = "dashboard_view"
                 WHERE user_id = :uid AND portal = :portal
                 ORDER BY created_at DESC LIMIT 1'
            );
            $stmt->execute([':uid' => $event->userId, ':portal' => $event->portal]);
        } catch (\Throwable) {}
    }

    public function onSearchPerformed(SearchPerformed $event): void
    {
        // Les statistiques de recherche seront exploitées par le module Rapports & BI
        // Pas de traitement synchrone — la trace est dans l'audit log.
    }

    public function onWidgetRefreshed(WidgetRefreshed $event): void
    {
        // Pas de traitement synchrone requis — extensible en V3 via analytics pipeline.
    }
}

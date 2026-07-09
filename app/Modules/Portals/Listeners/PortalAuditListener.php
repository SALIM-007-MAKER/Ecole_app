<?php
declare(strict_types=1);

namespace App\Modules\Portals\Listeners;

use App\Modules\Portals\Events\PortalAccessed;
use App\Modules\Portals\Events\DashboardViewed;
use App\Modules\Portals\Events\SearchPerformed;
use App\Modules\Portals\Events\PreferencesSaved;
use App\Modules\Portals\Events\ShortcutCreated;
use App\Modules\Portals\Events\ShortcutDeleted;
use App\Modules\Portals\Events\PortalError;
use App\Services\AuditService;
use Core\Database;
use Core\Event;
use Core\Listener;

class PortalAuditListener implements Listener
{
    private AuditService $audit;

    public function __construct()
    {
        $this->audit = new AuditService();
    }

    public function handle(Event $event): void
    {
        if ($event instanceof PortalAccessed) {
            $this->onPortalAccessed($event);
            return;
        }

        if ($event instanceof DashboardViewed) {
            $this->onDashboardViewed($event);
            return;
        }

        if ($event instanceof SearchPerformed) {
            $this->onSearchPerformed($event);
            return;
        }

        if ($event instanceof PreferencesSaved) {
            $this->onPreferencesSaved($event);
            return;
        }

        if ($event instanceof ShortcutCreated) {
            $this->onShortcutCreated($event);
            return;
        }

        if ($event instanceof ShortcutDeleted) {
            $this->onShortcutDeleted($event);
            return;
        }

        if ($event instanceof PortalError) {
            $this->onPortalError($event);
            return;
        }
    }

    public function onPortalAccessed(PortalAccessed $event): void
    {
        try {
            $this->logAccess($event->userId, $event->portal, $event->ip);
        } catch (\Throwable) {}
    }

    public function onDashboardViewed(DashboardViewed $event): void
    {
        try {
            $this->audit->log(
                $event->userId,
                'portal.dashboard.view',
                'portals',
                'portal',
                null,
                null,
                ['portal' => $event->portal, 'widgets' => $event->widgetCount]
            );
        } catch (\Throwable) {}
    }

    public function onSearchPerformed(SearchPerformed $event): void
    {
        try {
            $this->audit->log(
                $event->userId,
                'portal.search',
                'portals',
                null,
                null,
                null,
                ['portal' => $event->portal, 'query' => $event->query, 'results' => $event->resultCount]
            );
        } catch (\Throwable) {}
    }

    public function onPreferencesSaved(PreferencesSaved $event): void
    {
        try {
            $this->audit->log(
                $event->userId,
                'portal.preferences.saved',
                'portals',
                'portal_preferences',
                null,
                null,
                ['portal' => $event->portal]
            );
        } catch (\Throwable) {}
    }

    public function onShortcutCreated(ShortcutCreated $event): void
    {
        try {
            $this->audit->log(
                $event->userId,
                'portal.shortcut.create',
                'portals',
                null,
                null,
                null,
                ['portal' => $event->portal, 'label' => $event->label, 'url' => $event->url]
            );
        } catch (\Throwable) {}
    }

    public function onShortcutDeleted(ShortcutDeleted $event): void
    {
        try {
            $this->audit->log(
                $event->userId,
                'portal.shortcut.delete',
                'portals',
                null,
                $event->shortcutId,
                ['portal' => $event->portal],
                null
            );
        } catch (\Throwable) {}
    }

    public function onPortalError(PortalError $event): void
    {
        try {
            $this->audit->log(
                $event->userId,
                'portal.error',
                'portals',
                'error',
                null,
                null,
                ['portal' => $event->portal, 'context' => $event->context, 'message' => $event->message]
            );
        } catch (\Throwable) {}
    }

    private function logAccess(int $userId, string $portal, string $ip): void
    {
        try {
            $pdo  = Database::getInstance()->getConnection();
            $stmt = $pdo->prepare(
                'INSERT INTO portal_access_logs (user_id, portal, page, action, ip, user_agent, etablissement_id, created_at)
                 VALUES (:uid, :portal, :page, :action, :ip, :ua, 0, NOW())'
            );
            $stmt->execute([
                ':uid'    => $userId,
                ':portal' => $portal,
                ':page'   => $_SERVER['REQUEST_URI'] ?? '',
                ':action' => 'access',
                ':ip'     => $ip,
                ':ua'     => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
            ]);
        } catch (\Throwable) {}
    }
}

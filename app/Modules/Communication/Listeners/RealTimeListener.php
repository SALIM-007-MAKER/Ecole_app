<?php

declare(strict_types=1);

namespace App\Modules\Communication\Listeners;

use App\Modules\Communication\Events\NotificationCreated;
use App\Modules\Communication\Events\ThreadMessageSent;
use Core\Event;
use Core\Listener;

/**
 * Stub V2 — pas de WebSocket/SSE en V2.
 * V3 : intégrer Mercure ou Ratchet pour push temps réel.
 */
class RealTimeListener implements Listener
{
    public function handle(Event $event): void
    {
        // V2 no-op : les clients polleront /v2/notifications/poll toutes les 30s
        // V3 : pusher()->trigger('user.' . $userId, 'notification', $payload);
    }
}

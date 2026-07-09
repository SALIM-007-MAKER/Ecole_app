<?php

declare(strict_types=1);

namespace App\Modules\Communication\Listeners;

use App\Modules\Communication\Events\NotificationCreated;
use App\Modules\Communication\Events\NotificationRead;
use App\Modules\Communication\Events\NotificationsBulkRead;
use App\Modules\Communication\Events\ThreadCreated;
use App\Modules\Communication\Events\ThreadMessageSent;
use App\Modules\Communication\Events\MessageQueued;
use App\Modules\Communication\Events\MessageSent;
use App\Modules\Communication\Events\MessageFailed;
use App\Modules\Communication\Events\DiffusionEnvoyee;
use App\Modules\Communication\Events\CampagneLancee;
use App\Modules\Communication\Events\CampagneTerminee;
use App\Modules\Communication\Events\PreferencesUpdated;
use App\Services\AuditService;
use Core\Event;
use Core\Listener;

class AuditListener implements Listener
{
    public function handle(Event $event): void
    {
        match (true) {
            $event instanceof NotificationCreated  => AuditService::logCreate(
                $event->userId, 'notification_created', 'communication',
                $event->notificationId, ['type' => $event->type, 'titre' => $event->titre]
            ),
            $event instanceof NotificationRead     => AuditService::log(
                $event->userId, 'notification_read', 'communication',
                'notification', $event->notificationId
            ),
            $event instanceof NotificationsBulkRead => AuditService::log(
                $event->userId, 'notifications_bulk_read', 'communication',
                'notification', null, ['count' => $event->count]
            ),
            $event instanceof ThreadCreated        => AuditService::logCreate(
                $event->createdById, 'thread_created', 'communication',
                $event->threadId
            ),
            $event instanceof ThreadMessageSent    => AuditService::logCreate(
                $event->senderId, 'message_sent', 'communication',
                $event->messageId, ['thread_id' => $event->threadId]
            ),
            $event instanceof MessageSent          => AuditService::log(
                $event->userId, 'queue_sent', 'communication',
                'queue', $event->queueId, ['canal' => $event->canal, 'type' => $event->type]
            ),
            $event instanceof MessageFailed        => AuditService::log(
                $event->userId, 'queue_failed', 'communication',
                'queue', $event->queueId,
                ['canal' => $event->canal, 'raison' => $event->raison, 'tentative' => $event->tentative]
            ),
            $event instanceof DiffusionEnvoyee     => AuditService::log(
                $event->sentById, 'diffusion_envoyee', 'communication',
                'diffusion', null, ['type' => $event->type, 'total' => $event->totalDestinataires]
            ),
            $event instanceof CampagneLancee       => AuditService::logCreate(
                $event->launchedById, 'campagne_lancee', 'communication',
                $event->campagneId, ['total' => $event->totalDestinataires]
            ),
            $event instanceof CampagneTerminee     => AuditService::log(
                0, 'campagne_terminee', 'communication',
                'campagne', $event->campagneId, ['envoyes' => $event->totalEnvoyes, 'echecs' => $event->totalEchecs]
            ),
            $event instanceof PreferencesUpdated   => AuditService::log(
                $event->userId, 'preferences_updated', 'communication',
                'preference', null, $event->changes
            ),
            default => null,
        };
    }
}

<?php

namespace App\Modules\Academique\Listeners;

use Core\Listener;
use Core\Event;
use App\Modules\Academique\Events\BulletinGenerated;
use App\Modules\Academique\Events\BulletinPublished;
use App\Modules\Academique\Events\BulletinArchived;
use App\Services\AuditService;

class BulletinHandler implements Listener
{
    public function handle(Event $event): void
    {
        if ($event instanceof BulletinGenerated) {
            $this->onGenerated($event);
        } elseif ($event instanceof BulletinPublished) {
            $this->onPublished($event);
        } elseif ($event instanceof BulletinArchived) {
            $this->onArchived($event);
        }
    }

    private function onGenerated(BulletinGenerated $event): void
    {
        AuditService::logCreate(
            entity  : 'bulletin',
            entityId: $event->eleveId,
            userId  : $event->generatedById,
            details : [
                'classe_id'   => $event->classeId,
                'periode_id'  => $event->periodeId,
                'token'       => $event->verificationToken,
                'moyenne'     => $event->moyenne,
                'mention'     => $event->mentionCode,
                'rang'        => $event->rang,
                'nb_eleves'   => $event->nbEleves,
                'decision'    => $event->decision,
                'statut'      => $event->statut,
            ]
        );
    }

    private function onPublished(BulletinPublished $event): void
    {
        AuditService::logUpdate(
            entity  : 'bulletin',
            entityId: $event->eleveId,
            userId  : $event->publishedById,
            details : [
                'action'      => 'publie',
                'periode_id'  => $event->periodeId,
                'token'       => $event->verificationToken,
                'published_at'=> $event->publishedAt,
            ]
        );
    }

    private function onArchived(BulletinArchived $event): void
    {
        AuditService::logUpdate(
            entity  : 'bulletin',
            entityId: $event->eleveId,
            userId  : $event->archivedById,
            details : [
                'action'     => 'archive',
                'periode_id' => $event->periodeId,
                'token'      => $event->verificationToken,
                'archived_at'=> $event->archivedAt,
            ]
        );
    }
}

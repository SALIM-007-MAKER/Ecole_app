<?php

namespace App\Modules\Academique\Listeners;

use Core\Listener;
use Core\Event;
use App\Modules\Academique\Events\BulletinGenerated;
use App\Modules\Academique\Events\BulletinPublished;
use App\Modules\Academique\Events\BulletinArchived;
use App\Modules\Academique\Events\BulletinAppreciationUpdated;
use App\Modules\Academique\Services\BulletinTokenService;
use App\Services\AuditService;

/**
 * Audit des événements de bulletins. Le jeton de vérification complet n'est JAMAIS écrit dans le journal d'audit :
 * on y consigne une empreinte courte non réversible (BulletinTokenService::fingerprint) qui permet de recouper
 * des événements sans exposer un jeton utilisable. La clé s'appelle « jeton_empreinte » et non « token_… » : AuditService
 * masque (« *** ») toute clé contenant « token », ce qui effacerait aussi l'empreinte.
 */
class BulletinHandler implements Listener
{
    private AuditService $audit;

    public function __construct()
    {
        $this->audit = new AuditService();
    }

    public function handle(Event $event): void
    {
        if ($event instanceof BulletinGenerated) {
            $this->onGenerated($event);
        } elseif ($event instanceof BulletinPublished) {
            $this->onPublished($event);
        } elseif ($event instanceof BulletinArchived) {
            $this->onArchived($event);
        } elseif ($event instanceof BulletinAppreciationUpdated) {
            $this->onAppreciationUpdated($event);
        }
    }

    private function onGenerated(BulletinGenerated $event): void
    {
        $this->audit->logCreate(
            $event->generatedById,
            'academique',
            'bulletin',
            $event->eleveId,
            [
                'classe_id'   => $event->classeId,
                'periode_id'  => $event->periodeId,
                'jeton_empreinte'    => BulletinTokenService::fingerprint($event->verificationToken),
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
        $this->audit->log(
            $event->publishedById,
            'publier',
            'academique',
            'bulletin',
            $event->eleveId,
            null,
            [
                'periode_id'   => $event->periodeId,
                'jeton_empreinte'     => BulletinTokenService::fingerprint($event->verificationToken),
                'published_at' => $event->publishedAt,
            ]
        );
    }

    private function onArchived(BulletinArchived $event): void
    {
        $this->audit->log(
            $event->archivedById,
            'archiver',
            'academique',
            'bulletin',
            $event->eleveId,
            null,
            [
                'periode_id'  => $event->periodeId,
                'jeton_empreinte'    => BulletinTokenService::fingerprint($event->verificationToken),
                'archived_at' => $event->archivedAt,
            ]
        );
    }

    private function onAppreciationUpdated(BulletinAppreciationUpdated $event): void
    {
        $this->audit->log(
            $event->updatedById,
            'modifier_appreciation_directeur',
            'academique',
            'bulletin',
            $event->eleveId,
            null,
            [
                'periode_id' => $event->periodeId,
                'jeton_empreinte'   => BulletinTokenService::fingerprint($event->verificationToken),
            ]
        );
    }
}

<?php

namespace App\Modules\VieScolaire\Recompenses\Listeners;

use App\Modules\VieScolaire\Recompenses\Events\RewardGranted;
use App\Modules\VieScolaire\Recompenses\Events\RewardRevoked;
use App\Modules\VieScolaire\Recompenses\Events\RewardUpdated;
use Core\Event;
use Core\Listener;
use Core\Logger;

// MS2-M-004 : brancher NotificationService V2 pour envoi aux parents quand disponible
class NotificationListener implements Listener
{
    public function handle(Event $event): void
    {
        if ($event instanceof RewardGranted) {
            Logger::info('recompenses.notification', [
                'type'     => 'recompense_attribuee',
                'eleve_id' => $event->eleveId,
                'niveau'   => $event->niveau,
                'deferred' => true,
            ]);
        } elseif ($event instanceof RewardRevoked) {
            Logger::info('recompenses.notification', [
                'type'     => 'recompense_revoquee',
                'eleve_id' => $event->eleveId,
                'deferred' => true,
            ]);
        } elseif ($event instanceof RewardUpdated) {
            Logger::info('recompenses.notification', [
                'type'     => 'recompense_modifiee',
                'eleve_id' => $event->eleveId,
                'deferred' => true,
            ]);
        }
    }
}

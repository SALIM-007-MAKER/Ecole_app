<?php

namespace App\Modules\VieScolaire\Recompenses\Listeners;

use App\Modules\VieScolaire\Recompenses\Events\RewardGranted;
use App\Modules\VieScolaire\Recompenses\Events\RewardRevoked;
use App\Modules\VieScolaire\Recompenses\Events\RewardUpdated;
use Core\Event;
use Core\Listener;
use Core\Logger;

class AuditListener implements Listener
{
    public function handle(Event $event): void
    {
        if ($event instanceof RewardGranted) {
            Logger::info('recompenses.audit', [
                'action'       => 'recompense_attribuee',
                'reward_id'    => $event->rewardId,
                'eleve_id'     => $event->eleveId,
                'classe_id'    => $event->classeId,
                'categorie'    => $event->categorieCode,
                'niveau'       => $event->niveau,
                'attribue_par' => $event->attribueParId,
            ]);
        } elseif ($event instanceof RewardUpdated) {
            Logger::info('recompenses.audit', [
                'action'      => 'recompense_modifiee',
                'reward_id'   => $event->rewardId,
                'eleve_id'    => $event->eleveId,
                'modifie_par' => $event->modifieParId,
            ]);
        } elseif ($event instanceof RewardRevoked) {
            Logger::warning('recompenses.audit', [
                'action'      => 'recompense_revoquee',
                'reward_id'   => $event->rewardId,
                'eleve_id'    => $event->eleveId,
                'motif'       => $event->motifRevocation,
                'revoque_par' => $event->revoqueParId,
            ]);
        }
    }
}

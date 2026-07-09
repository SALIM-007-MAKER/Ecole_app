<?php

namespace App\Modules\VieScolaire\Recompenses\Listeners;

use App\Modules\VieScolaire\Recompenses\Events\RewardGranted;
use App\Modules\VieScolaire\Recompenses\Repositories\RewardRepository;
use Core\Event;
use Core\Listener;
use Core\Logger;

class StatisticsListener implements Listener
{
    public function handle(Event $event): void
    {
        if (!($event instanceof RewardGranted)) {
            return;
        }

        try {
            $repo  = new RewardRepository();
            $total = $repo->countByEleveAndAnnee($event->eleveId, $event->anneeScolaire);

            Logger::info('recompenses.statistics', [
                'action'           => 'recompense_comptabilisee',
                'eleve_id'         => $event->eleveId,
                'annee'            => $event->anneeScolaire,
                'total_recompenses'=> $total,
                'categorie'        => $event->categorieCode,
                'niveau'           => $event->niveau,
            ]);
        } catch (\Throwable $e) {
            Logger::warning('recompenses.statistics.error', [
                'message'  => $e->getMessage(),
                'eleve_id' => $event->eleveId,
            ]);
        }
    }
}

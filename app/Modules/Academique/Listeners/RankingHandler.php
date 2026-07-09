<?php

namespace App\Modules\Academique\Listeners;

use Core\Listener;
use Core\Event;
use App\Modules\Academique\Events\RankingGenerated;
use App\Modules\Academique\Events\RankingUpdated;
use App\Services\AuditService;

class RankingHandler implements Listener
{
    public function handle(Event $event): void
    {
        if ($event instanceof RankingGenerated) {
            $this->onGenerated($event);
        } elseif ($event instanceof RankingUpdated) {
            $this->onUpdated($event);
        }
    }

    private function onGenerated(RankingGenerated $event): void
    {
        AuditService::log(
            action : 'ranking.generated',
            entity : 'classement',
            entityId: $event->classeId ?? 0,
            userId  : $event->generatedById,
            details : [
                'type'          => $event->type,
                'periode_id'    => $event->periodeId,
                'classe_id'     => $event->classeId,
                'matiere_id'    => $event->matiereId,
                'nb_eleves'     => $event->nbEleves,
                'moyenne'       => $event->moyenneClasse,
                'taux_reussite' => $event->tauxReussite,
            ]
        );
    }

    private function onUpdated(RankingUpdated $event): void
    {
        AuditService::log(
            action : 'ranking.updated',
            entity : 'classement',
            entityId: $event->classeId ?? 0,
            userId  : $event->updatedById,
            details : [
                'type'       => $event->type,
                'periode_id' => $event->periodeId,
                'motif'      => $event->motif,
            ]
        );
    }
}

<?php

namespace App\Modules\Academique\Listeners;

use Core\Listener;
use Core\Event;
use App\Modules\Academique\Events\RankingGenerated;
use App\Modules\Academique\Events\RankingUpdated;
use App\Services\AuditService;

class RankingHandler implements Listener
{
    private AuditService $audit;

    public function __construct()
    {
        $this->audit = new AuditService();
    }

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
        $this->audit->log(
            $event->generatedById,
            'ranking.generated',
            'academique',
            'classement',
            $event->classeId ?? 0,
            null,
            [
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
        $this->audit->log(
            $event->updatedById,
            'ranking.updated',
            'academique',
            'classement',
            $event->classeId ?? 0,
            null,
            [
                'type'       => $event->type,
                'periode_id' => $event->periodeId,
                'motif'      => $event->motif,
            ]
        );
    }
}

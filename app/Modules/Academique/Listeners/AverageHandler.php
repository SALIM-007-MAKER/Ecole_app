<?php

namespace App\Modules\Academique\Listeners;

use App\Modules\Academique\Events\AverageCalculated;
use App\Modules\Academique\Events\ClassAverageUpdated;
use App\Services\AuditService;
use Core\Event;
use Core\Listener;

class AverageHandler implements Listener
{
    private AuditService $audit;

    public function __construct()
    {
        $this->audit = new AuditService();
    }

    public function handle(Event $event): void
    {
        match(true) {
            $event instanceof AverageCalculated   => $this->onCalculated($event),
            $event instanceof ClassAverageUpdated => $this->onClassUpdated($event),
            default                               => null,
        };
    }

    private function onCalculated(AverageCalculated $e): void
    {
        $this->audit->log(
            $e->calculatedById,
            'calculate',
            'academique',
            'moyenne_eleve',
            $e->eleveId,
            null,
            ['periode_id' => $e->periodeId, 'matiere_id' => $e->matiereId, 'moyenne' => $e->moyenne]
        );
    }

    private function onClassUpdated(ClassAverageUpdated $e): void
    {
        $this->audit->log(
            $e->updatedById,
            'update',
            'academique',
            'moyenne_classe',
            $e->classeId,
            null,
            ['periode_id' => $e->periodeId, 'moyenne' => $e->moyenne, 'taux_reussite' => $e->tauxReussite]
        );
    }
}

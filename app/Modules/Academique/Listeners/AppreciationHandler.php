<?php

namespace App\Modules\Academique\Listeners;

use App\Modules\Academique\Events\AppreciationMatiereSaisie;
use App\Services\AuditService;
use Core\Event;
use Core\Listener;

class AppreciationHandler implements Listener
{
    private AuditService $audit;

    public function __construct()
    {
        $this->audit = new AuditService();
    }

    public function handle(Event $event): void
    {
        if (!$event instanceof AppreciationMatiereSaisie) {
            return;
        }

        $this->audit->log(
            $event->saisieById,
            $event->created ? 'create' : 'update',
            'academique',
            'appreciation_matiere',
            $event->appreciationId,
            null,
            ['eleve_id' => $event->eleveId, 'matiere_id' => $event->matiereId, 'periode_id' => $event->periodeId]
        );
    }
}

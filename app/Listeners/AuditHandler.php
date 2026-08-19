<?php

namespace App\Listeners;

use Core\Event;
use Core\Listener;
use App\Services\AuditService;
use App\Events\EleveCreated;
use App\Events\AbsenceCreee;
use App\Events\DocumentGenere;
use App\Events\ImportCsvCompleted;

class AuditHandler implements Listener
{
    private AuditService $audit;

    public function __construct()
    {
        $this->audit = new AuditService();
    }

    public function handle(Event $event): void
    {
        match (true) {
            $event instanceof EleveCreated   => $this->audit->logCreate(
                $event->createdById,
                'eleves',
                'eleves',
                $event->eleveId,
                $event->toArray()
            ),

            $event instanceof AbsenceCreee   => $this->audit->logCreate(
                $event->saisieParId,
                'absences',
                'absences',
                $event->absenceId ?: null,
                $event->toArray()
            ),

            $event instanceof DocumentGenere => $this->audit->log(
                $event->genereParId,
                'export',
                'documents',
                null,
                null,
                null,
                $event->toArray()
            ),

            $event instanceof ImportCsvCompleted => $this->audit->log(
                $event->importedById,
                'import',
                'eleves',
                'eleves',
                null,
                null,
                $event->toArray()
            ),

            default => null,
        };
    }
}

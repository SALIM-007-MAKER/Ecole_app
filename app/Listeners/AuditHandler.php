<?php

namespace App\Listeners;

use Core\Event;
use Core\Listener;
use App\Services\AuditService;
use App\Events\EleveCreated;
use App\Events\EleveActifChanged;
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
                // Identifiants seulement : ni nom ni prénom d'élève dans le journal (donnée personnelle non nécessaire à la traçabilité).
                [
                    'eleve_id'        => $event->eleveId,
                    'matricule'       => $event->matricule,
                    'classe_id'       => $event->classeId,
                    'from_csv_import' => $event->fromCsvImport,
                ]
            ),

            $event instanceof EleveActifChanged => $this->audit->log(
                $event->changedById,
                $event->nouvelActif ? 'reactiver' : 'desactiver',
                'eleves',
                'eleves',
                $event->eleveId,
                ['actif' => $event->ancienActif ? 1 : 0],
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

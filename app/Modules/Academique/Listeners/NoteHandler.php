<?php

namespace App\Modules\Academique\Listeners;

use App\Modules\Academique\Events\NoteCreated;
use App\Modules\Academique\Events\NoteImported;
use App\Modules\Academique\Events\NoteLocked;
use App\Modules\Academique\Events\NotePublished;
use App\Modules\Academique\Events\NoteUpdated;
use App\Services\AuditService;
use Core\Event;
use Core\Listener;

class NoteHandler implements Listener
{
    private AuditService $audit;

    public function __construct()
    {
        $this->audit = new AuditService();
    }

    public function handle(Event $event): void
    {
        match(true) {
            $event instanceof NoteCreated   => $this->onCreated($event),
            $event instanceof NoteUpdated   => $this->onUpdated($event),
            $event instanceof NotePublished => $this->onPublished($event),
            $event instanceof NoteLocked    => $this->onLocked($event),
            $event instanceof NoteImported  => $this->onImported($event),
            default                         => null,
        };
    }

    private function onCreated(NoteCreated $e): void
    {
        $this->audit->logCreate(
            $e->createdById,
            'academique',
            'note',
            $e->noteId,
            ['evaluation_id' => $e->evaluationId, 'eleve_id' => $e->eleveId, 'valeur' => $e->valeur]
        );
    }

    private function onUpdated(NoteUpdated $e): void
    {
        $this->audit->log(
            $e->updatedById,
            'update',
            'academique',
            'note',
            $e->noteId,
            ['valeur' => $e->oldValeur],
            ['valeur' => $e->newValeur]
        );
    }

    private function onPublished(NotePublished $e): void
    {
        $this->audit->log(
            $e->publishedById,
            'publier',
            'academique',
            'evaluation',
            $e->evaluationId,
            null,
            ['count' => $e->count]
        );
    }

    private function onLocked(NoteLocked $e): void
    {
        $this->audit->log(
            $e->lockedById,
            'verrouiller',
            'academique',
            'evaluation',
            $e->evaluationId,
            null,
            ['count' => $e->count]
        );
    }

    private function onImported(NoteImported $e): void
    {
        $this->audit->log(
            $e->importedById,
            'importer',
            'academique',
            'evaluation',
            $e->evaluationId,
            null,
            ['created' => $e->created, 'updated' => $e->updated, 'errors' => $e->errors]
        );
    }
}

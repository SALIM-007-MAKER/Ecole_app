<?php

declare(strict_types=1);

namespace App\Modules\RH\Documents\Listeners;

use Core\Listener;
use Core\Event;
use App\Services\AuditService;
use App\Modules\RH\Documents\Events\HRDocumentCreated;
use App\Modules\RH\Documents\Events\HRDocumentUpdated;
use App\Modules\RH\Documents\Events\HRDocumentExpired;
use App\Modules\RH\Documents\Events\HRDocumentArchived;

class AuditListener implements Listener
{
    private AuditService $audit;

    public function __construct()
    {
        $this->audit = new AuditService();
    }

    public function handle(Event $event): void
    {
        match (true) {
            $event instanceof HRDocumentCreated  => $this->audit->logCreate(
                $event->createdBy, 'RH', 'hr_document', $event->documentId,
                ['type' => $event->type, 'titre' => $event->titre, 'employe_id' => $event->employeId]
            ),
            $event instanceof HRDocumentUpdated  => $this->audit->log(
                $event->updatedBy, $event->action, 'RH', 'hr_document', $event->documentId,
                ['version' => $event->version - 1], ['version' => $event->version]
            ),
            $event instanceof HRDocumentExpired  => $this->audit->log(
                0, 'expire', 'RH', 'hr_document', $event->documentId,
                ['statut' => 'actif'], ['statut' => 'expire', 'date_expiration' => $event->dateExpiration]
            ),
            $event instanceof HRDocumentArchived => $this->audit->log(
                $event->archivedBy, 'archiver', 'RH', 'hr_document', $event->documentId,
                ['statut' => $event->ancienStatut], ['statut' => 'archive']
            ),
            default => null,
        };
    }
}

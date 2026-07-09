<?php

declare(strict_types=1);

namespace App\Modules\Documents\Listeners;

use Core\Event;
use Core\Listener;
use App\Services\AuditService;
use App\Modules\Documents\Events\DocumentUploaded;
use App\Modules\Documents\Events\DocumentVersioned;
use App\Modules\Documents\Events\DocumentArchived;
use App\Modules\Documents\Events\DocumentRestored;
use App\Modules\Documents\Events\DocumentTrashed;
use App\Modules\Documents\Events\DocumentPurged;
use App\Modules\Documents\Events\DocumentShared;
use App\Modules\Documents\Events\DocumentShareRevoked;
use App\Modules\Documents\Events\DocumentTagged;
use App\Modules\Documents\Events\DocumentMoved;
use App\Modules\Documents\Events\DocumentExpired;
use App\Modules\Documents\Events\DocumentSignatureRequested;
use App\Modules\Documents\Events\DocumentSigned;
use App\Modules\Documents\Events\FolderCreated;
use App\Modules\Documents\Events\FolderDeleted;
use App\Modules\Documents\Events\QuotaExceeded;

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
            $event instanceof DocumentUploaded           => $this->onUploaded($event),
            $event instanceof DocumentVersioned          => $this->onVersioned($event),
            $event instanceof DocumentArchived           => $this->onArchived($event),
            $event instanceof DocumentRestored           => $this->onRestored($event),
            $event instanceof DocumentTrashed            => $this->onTrashed($event),
            $event instanceof DocumentPurged             => $this->onPurged($event),
            $event instanceof DocumentShared             => $this->onShared($event),
            $event instanceof DocumentShareRevoked       => $this->onShareRevoked($event),
            $event instanceof DocumentTagged             => $this->onTagged($event),
            $event instanceof DocumentMoved              => $this->onMoved($event),
            $event instanceof DocumentExpired            => $this->onExpired($event),
            $event instanceof DocumentSignatureRequested => $this->onSignatureRequested($event),
            $event instanceof DocumentSigned             => $this->onSigned($event),
            $event instanceof FolderCreated              => $this->onFolderCreated($event),
            $event instanceof FolderDeleted              => $this->onFolderDeleted($event),
            $event instanceof QuotaExceeded              => $this->onQuotaExceeded($event),
            default => null,
        };
    }

    private function onUploaded(DocumentUploaded $e): void
    {
        $this->audit->logCreate($e->uploadedById, 'documents', 'document', $e->documentId, [
            'module_source' => $e->moduleSource,
            'mime_type'     => $e->mimeType,
            'taille_octets' => $e->tailleOctets,
        ]);
    }

    private function onVersioned(DocumentVersioned $e): void
    {
        $this->audit->log($e->updatedById, 'version', 'documents', 'document', $e->documentId,
            ['version' => $e->oldVersion],
            ['version' => $e->newVersion, 'taille_octets' => $e->tailleOctets]
        );
    }

    private function onArchived(DocumentArchived $e): void
    {
        $this->audit->log($e->archivedById, 'archive', 'documents', 'document', $e->documentId,
            ['statut' => $e->ancienStatut], ['statut' => 'archive']
        );
    }

    private function onRestored(DocumentRestored $e): void
    {
        $this->audit->log($e->restoredById, 'restore', 'documents', 'document', $e->documentId,
            ['statut' => $e->ancienStatut], ['statut' => $e->nouveauStatut]
        );
    }

    private function onTrashed(DocumentTrashed $e): void
    {
        $this->audit->log($e->deletedById, 'trash', 'documents', 'document', $e->documentId,
            null, ['statut' => 'corbeille']
        );
    }

    private function onPurged(DocumentPurged $e): void
    {
        $this->audit->log($e->purgedById, 'purge', 'documents', 'document', $e->documentId,
            ['chemin' => $e->cheminSupprime], null
        );
    }

    private function onShared(DocumentShared $e): void
    {
        $this->audit->log($e->sharedById, 'share', 'documents', 'document', $e->documentId,
            null, [
                'destinataire_type' => $e->destinataireType,
                'destinataire_id'   => $e->destinataireId,
                'permission'        => $e->permission,
            ]
        );
    }

    private function onShareRevoked(DocumentShareRevoked $e): void
    {
        $this->audit->log($e->revokedById, 'revoke_share', 'documents', 'document', $e->documentId,
            ['partage_id' => $e->partageId], null
        );
    }

    private function onTagged(DocumentTagged $e): void
    {
        $this->audit->log($e->taggedById, 'tag', 'documents', 'document', $e->documentId,
            null, ['added' => $e->tagIdsAdded, 'removed' => $e->tagIdsRemoved]
        );
    }

    private function onMoved(DocumentMoved $e): void
    {
        $this->audit->log($e->movedById, 'move', 'documents', 'document', $e->documentId,
            ['folder_id' => $e->oldFolderId], ['folder_id' => $e->newFolderId]
        );
    }

    private function onExpired(DocumentExpired $e): void
    {
        $this->audit->log(null, 'expire', 'documents', 'document', $e->documentId,
            null, ['date_expiration' => $e->dateExpiration]
        );
    }

    private function onSignatureRequested(DocumentSignatureRequested $e): void
    {
        $this->audit->log($e->requestedById, 'signature_request', 'documents', 'document', $e->documentId,
            null, ['signataires' => $e->signataires]
        );
    }

    private function onSigned(DocumentSigned $e): void
    {
        $this->audit->log($e->signataireId, 'sign', 'documents', 'document', $e->documentId,
            null, ['signed_at' => $e->signedAt, 'completion' => $e->completionPercent]
        );
    }

    private function onFolderCreated(FolderCreated $e): void
    {
        $this->audit->logCreate($e->createdById, 'documents', 'folder', $e->folderId, [
            'parent_id'     => $e->parentId,
            'module_source' => $e->moduleSource,
        ]);
    }

    private function onFolderDeleted(FolderDeleted $e): void
    {
        $this->audit->log($e->deletedById, 'delete', 'documents', 'folder', $e->folderId,
            null, null
        );
    }

    private function onQuotaExceeded(QuotaExceeded $e): void
    {
        $this->audit->log(null, 'quota_exceeded', 'documents', 'quota', null,
            null, [
                'module_source'  => $e->moduleSource,
                'quota_octets'   => $e->quotaOctets,
                'utilise_octets' => $e->utilisOctets,
            ]
        );
    }
}

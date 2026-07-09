<?php

namespace App\Modules\Finance\Listeners;

use Core\Event;
use Core\Listener;
use App\Modules\Finance\Events\InvoiceCreated;
use App\Modules\Finance\Events\InvoiceGenerated;
use App\Modules\Finance\Events\InvoiceCancelled;
use App\Modules\Finance\Events\InvoiceUpdated;
use App\Modules\Finance\Events\InvoiceArchived;
use App\Services\AuditService;

class InvoiceHandler implements Listener
{
    private AuditService $audit;

    public function __construct()
    {
        $this->audit = new AuditService();
    }

    public function handle(Event $event): void
    {
        match (true) {
            $event instanceof InvoiceCreated   => $this->onCreated($event),
            $event instanceof InvoiceGenerated => $this->onGenerated($event),
            $event instanceof InvoiceCancelled => $this->onCancelled($event),
            $event instanceof InvoiceUpdated   => $this->onUpdated($event),
            $event instanceof InvoiceArchived  => $this->onArchived($event),
            default                            => null,
        };
    }

    private function onCreated(InvoiceCreated $e): void
    {
        $this->audit->log(
            $e->createdById,
            'invoice.created',
            'finance',
            'facture',
            $e->factureId,
            null,
            $e->toArray()
        );
    }

    private function onGenerated(InvoiceGenerated $e): void
    {
        $this->audit->log(
            $e->generatedById,
            'invoice.bulk_generated',
            'finance',
            null,
            null,
            null,
            $e->toArray()
        );
    }

    private function onCancelled(InvoiceCancelled $e): void
    {
        $this->audit->log(
            $e->cancelledById,
            'invoice.cancelled',
            'finance',
            'facture',
            $e->factureId,
            ['statut' => 'emise'],
            ['statut' => 'annulee', 'motif' => $e->motif, 'avoir_id' => $e->avoirId]
        );
    }

    private function onUpdated(InvoiceUpdated $e): void
    {
        $this->audit->log(
            $e->updatedById,
            'invoice.updated',
            'finance',
            'facture',
            $e->factureId,
            null,
            $e->toArray()
        );
    }

    private function onArchived(InvoiceArchived $e): void
    {
        $this->audit->log(
            $e->archivedById,
            'invoice.archived',
            'finance',
            'facture',
            $e->factureId,
            ['statut' => $e->ancienStatut],
            ['statut' => 'archive']
        );
    }
}

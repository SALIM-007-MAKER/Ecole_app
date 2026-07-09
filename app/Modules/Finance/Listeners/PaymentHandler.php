<?php

namespace App\Modules\Finance\Listeners;

use Core\Event;
use Core\Listener;
use App\Modules\Finance\Events\PaymentInitiated;
use App\Modules\Finance\Events\PaymentCompleted;
use App\Modules\Finance\Events\PaymentPartial;
use App\Modules\Finance\Events\PaymentRefunded;
use App\Modules\Finance\Events\PaymentCancelled;
use App\Modules\Finance\Events\ReceiptGenerated;
use App\Services\AuditService;

class PaymentHandler implements Listener
{
    private AuditService $audit;

    public function __construct()
    {
        $this->audit = new AuditService();
    }

    public function handle(Event $event): void
    {
        match (true) {
            $event instanceof PaymentInitiated  => $this->onInitiated($event),
            $event instanceof PaymentCompleted  => $this->onCompleted($event),
            $event instanceof PaymentPartial    => $this->onPartial($event),
            $event instanceof PaymentRefunded   => $this->onRefunded($event),
            $event instanceof PaymentCancelled  => $this->onCancelled($event),
            $event instanceof ReceiptGenerated  => $this->onReceiptGenerated($event),
            default                             => null,
        };
    }

    private function onInitiated(PaymentInitiated $e): void
    {
        $this->audit->log(
            $e->initiatedById,
            'payment.initiated',
            'finance',
            'paiement',
            $e->paiementId,
            null,
            $e->toArray()
        );
    }

    private function onCompleted(PaymentCompleted $e): void
    {
        $this->audit->log(
            $e->completedById,
            'payment.completed',
            'finance',
            'paiement',
            $e->paiementId,
            ['statut' => 'initie'],
            $e->toArray()
        );
    }

    private function onPartial(PaymentPartial $e): void
    {
        $this->audit->log(
            $e->completedById,
            'payment.partial',
            'finance',
            'paiement',
            $e->paiementId,
            null,
            $e->toArray()
        );
    }

    private function onRefunded(PaymentRefunded $e): void
    {
        $this->audit->log(
            $e->refundedById,
            'payment.refunded',
            'finance',
            'paiement',
            $e->paiementId,
            ['statut' => 'complete'],
            $e->toArray()
        );
    }

    private function onCancelled(PaymentCancelled $e): void
    {
        $this->audit->log(
            $e->cancelledById,
            'payment.cancelled',
            'finance',
            'paiement',
            $e->paiementId,
            ['statut' => $e->ancienStatut],
            ['statut' => 'annule', 'motif' => $e->motif]
        );
    }

    private function onReceiptGenerated(ReceiptGenerated $e): void
    {
        $this->audit->log(
            $e->generatedById,
            'receipt.generated',
            'finance',
            'recu',
            $e->recuId,
            null,
            $e->toArray()
        );
    }
}

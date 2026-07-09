<?php

namespace App\Modules\Finance\Listeners;

use Core\Event;
use Core\Listener;
use App\Modules\Finance\Events\CashRegisterOpened;
use App\Modules\Finance\Events\CashRegisterClosed;
use App\Modules\Finance\Events\CashMovementCreated;
use App\Modules\Finance\Events\CashMovementCancelled;
use App\Modules\Finance\Events\CashBalanceUpdated;
use App\Modules\Finance\Events\PaymentCompleted;
use App\Modules\Finance\Services\CashRegisterService;
use App\Services\AuditService;

class CashRegisterHandler implements Listener
{
    private AuditService        $audit;
    private CashRegisterService $cashService;

    public function __construct()
    {
        $this->audit       = new AuditService();
        $this->cashService = new CashRegisterService();
    }

    public function handle(Event $event): void
    {
        match (true) {
            $event instanceof CashRegisterOpened   => $this->onOpened($event),
            $event instanceof CashRegisterClosed   => $this->onClosed($event),
            $event instanceof CashMovementCreated  => $this->onMovementCreated($event),
            $event instanceof CashMovementCancelled => $this->onMovementCancelled($event),
            $event instanceof CashBalanceUpdated   => $this->onBalanceUpdated($event),
            $event instanceof PaymentCompleted     => $this->onPaymentCompleted($event),
            default                                => null,
        };
    }

    private function onOpened(CashRegisterOpened $e): void
    {
        $this->audit->log(
            $e->openedById,
            'caisse.ouvrir',
            'finance',
            'session_caisse',
            $e->sessionId,
            null,
            $e->toArray()
        );
    }

    private function onClosed(CashRegisterClosed $e): void
    {
        $this->audit->log(
            $e->closedById,
            'caisse.fermer',
            'finance',
            'session_caisse',
            $e->sessionId,
            ['statut' => 'en_activite'],
            $e->toArray()
        );
    }

    private function onMovementCreated(CashMovementCreated $e): void
    {
        $this->audit->log(
            $e->createdById,
            'caisse.mouvement.create',
            'finance',
            'mouvement_caisse',
            $e->mouvementId,
            null,
            $e->toArray()
        );
    }

    private function onMovementCancelled(CashMovementCancelled $e): void
    {
        $this->audit->log(
            $e->cancelledById,
            'caisse.mouvement.annuler',
            'finance',
            'mouvement_caisse',
            $e->mouvementId,
            ['statut' => 'actif'],
            ['statut' => 'annule', 'motif' => $e->motif]
        );
    }

    private function onBalanceUpdated(CashBalanceUpdated $e): void
    {
        $this->audit->log(
            $e->updatedById,
            'caisse.balance.update',
            'finance',
            'session_caisse',
            $e->sessionId,
            ['solde' => $e->ancienSolde],
            ['solde' => $e->nouveauSolde]
        );
    }

    // Alimentation automatique de la caisse depuis un paiement complété
    private function onPaymentCompleted(PaymentCompleted $e): void
    {
        try {
            $this->cashService->crediterDepuisPaiement(
                $e->paiementId,
                $e->numero,
                $e->montantApplique,
                $e->modePaiement,
                $e->completedById,
            );
        } catch (\Throwable $ex) {
            // Ne pas faire échouer le paiement si la caisse est indisponible
            // La réconciliation manuelle reste possible
            error_log('[CashRegisterHandler] crediterDepuisPaiement failed: ' . $ex->getMessage());
        }
    }
}

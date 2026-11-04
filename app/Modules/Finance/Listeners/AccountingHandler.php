<?php

namespace App\Modules\Finance\Listeners;

use App\Modules\Finance\Events\JournalEntryCreated;
use App\Modules\Finance\Events\FiscalYearClosed;
use App\Modules\Finance\Events\FiscalYearOpened;
use App\Modules\Finance\Events\PaymentCompleted;
use App\Modules\Finance\Events\PaymentRefunded;
use App\Modules\Finance\Events\CashMovementCreated;
use App\Modules\Finance\Events\ExpenseValidated;
use App\Modules\Finance\Events\InvoiceCancelled;
use App\Modules\Finance\Services\AccountingService;
use App\Services\AuditService;
use Core\Event;
use Core\Listener;

class AccountingHandler implements Listener
{
    private AccountingService $accountingService;
    private AuditService      $audit;

    public function __construct()
    {
        $this->accountingService = new AccountingService();
        $this->audit             = new AuditService();
    }

    public function handle(Event $event): void
    {
        match (true) {
            $event instanceof PaymentCompleted    => $this->onPaymentCompleted($event),
            $event instanceof PaymentRefunded     => $this->onPaymentRefunded($event),
            $event instanceof CashMovementCreated => $this->onCashMovementCreated($event),
            $event instanceof ExpenseValidated    => $this->onExpenseValidated($event),
            $event instanceof InvoiceCancelled    => $this->onInvoiceCancelled($event),
            $event instanceof JournalEntryCreated => $this->onJournalEntryCreated($event),
            $event instanceof FiscalYearClosed    => $this->onFiscalYearClosed($event),
            $event instanceof FiscalYearOpened    => $this->onFiscalYearOpened($event),
            default => null,
        };
    }

    // ── Listeners événements métier (génération automatique des écritures) ────

    private function onPaymentCompleted(PaymentCompleted $e): void
    {
        try {
            $this->accountingService->enregistrerDepuisPaiement($e);
        } catch (\Throwable $ex) {
            // Échec silencieux — le paiement ne doit pas être bloqué par la comptabilité
            error_log('[AccountingHandler] enregistrerDepuisPaiement failed: ' . $ex->getMessage());
        }
    }

    private function onPaymentRefunded(PaymentRefunded $e): void
    {
        try {
            $this->accountingService->enregistrerDepuisRemboursement($e);
        } catch (\Throwable $ex) {
            error_log('[AccountingHandler] enregistrerDepuisRemboursement failed: ' . $ex->getMessage());
        }
    }

    private function onCashMovementCreated(CashMovementCreated $e): void
    {
        try {
            $this->accountingService->enregistrerDepuisMouvementCaisse($e);
        } catch (\Throwable $ex) {
            error_log('[AccountingHandler] enregistrerDepuisMouvementCaisse failed: ' . $ex->getMessage());
        }
    }

    private function onExpenseValidated(ExpenseValidated $e): void
    {
        try {
            $this->accountingService->enregistrerDepuisDepense($e);
        } catch (\Throwable $ex) {
            error_log('[AccountingHandler] enregistrerDepuisDepense failed: ' . $ex->getMessage());
        }
    }

    private function onInvoiceCancelled(InvoiceCancelled $e): void
    {
        try {
            $this->accountingService->enregistrerDepuisAnnulationFacture($e);
        } catch (\Throwable $ex) {
            error_log('[AccountingHandler] enregistrerDepuisAnnulationFacture failed: ' . $ex->getMessage());
        }
    }

    // ── Listeners événements comptabilité (audit) ─────────────────────────────

    private function onJournalEntryCreated(JournalEntryCreated $e): void
    {
        $this->audit->log(
            $e->createdById,
            'create',
            'Finance',
            'ecriture_comptable',
            $e->ecritureId,
            null,
            $e->toArray()
        );
    }

    private function onFiscalYearClosed(FiscalYearClosed $e): void
    {
        // Aucune écriture d'audit ici : la clôture d'exercice est une opération CRITIQUE dont la trace unique est écrite par
        // AccountingService::cloturerExercice() DANS la transaction de clôture (annulée si l'audit échoue). L'écrire aussi ici (au mieux,
        // après validation) produisait deux entrées pour une même clôture. L'événement reste diffusé pour d'éventuels autres écouteurs.
    }

    private function onFiscalYearOpened(FiscalYearOpened $e): void
    {
        // Aucune écriture d'audit ici : la trace unique de création est écrite par AccountingService::creerExercice() DANS la transaction.
        // L'écrire aussi ici produisait deux entrées pour un même exercice. L'événement reste diffusé pour d'éventuels autres écouteurs.
    }
}

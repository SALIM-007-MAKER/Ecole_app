<?php

namespace App\Modules\Finance\Listeners;

use App\Models\EleveModel;
use App\Modules\Finance\Events\PaymentCompleted;
use App\Modules\Finance\Repositories\InvoiceRepository;
use App\Services\NotificationService;
use Core\Event;
use Core\Listener;
use Core\Logger;

/**
 * Notifie le parent lors d'un paiement complété — équivalent V2 de
 * NotificationService::onPaiement() (V1), qui ne réagit qu'aux paiements
 * enregistrés via l'ancien contrôleur et ne voit donc jamais les paiements
 * V2. Sans ce listener, un parent ne reçoit plus aucune notification de
 * paiement dès que le personnel utilise l'interface V2.
 */
class NotificationListener implements Listener
{
    private NotificationService $notif;
    private InvoiceRepository   $invoiceRepo;
    private EleveModel          $eleveModel;

    public function __construct()
    {
        $this->notif       = new NotificationService();
        $this->invoiceRepo = new InvoiceRepository();
        $this->eleveModel  = new EleveModel();
    }

    public function handle(Event $event): void
    {
        if ($event instanceof PaymentCompleted) {
            $this->onPaymentCompleted($event);
        }
    }

    private function onPaymentCompleted(PaymentCompleted $e): void
    {
        try {
            $eleve = $this->eleveModel->findById($e->eleveId);
            if (!$eleve || empty($eleve->parent_id)) {
                return;
            }

            $facture   = $this->invoiceRepo->findWithDetails($e->factureId);
            $montantF  = number_format($e->montantApplique, 0, ',', ' ');
            $fraisInfo = $facture ? " (facture {$facture->numero})" : '';

            $this->notif->notify(
                (int)$eleve->parent_id,
                'paiement',
                "Paiement reçu — {$montantF} XOF",
                "Un paiement de {$montantF} XOF a été enregistré pour {$eleve->prenom} {$eleve->nom}{$fraisInfo}.",
                BASE_URL . '/v2/finance/mes-paiements'
            );
        } catch (\Throwable $ex) {
            Logger::warning('[Finance/Notif] échec notification PaymentCompleted : ' . $ex->getMessage());
        }
    }
}

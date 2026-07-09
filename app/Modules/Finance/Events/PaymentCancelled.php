<?php

namespace App\Modules\Finance\Events;

use Core\Event;

class PaymentCancelled extends Event
{
    public function __construct(
        public readonly int    $paiementId,
        public readonly string $numero,
        public readonly int    $factureId,
        public readonly float  $montantAnnule,
        public readonly string $ancienStatut,
        public readonly string $motif,
        public readonly int    $cancelledById,
    ) {
        parent::__construct();
    }

    public function getName(): string
    {
        return 'finance.payment.cancelled';
    }

    public function toArray(): array
    {
        return [
            'paiement_id'   => $this->paiementId,
            'numero'        => $this->numero,
            'facture_id'    => $this->factureId,
            'montant_annule' => $this->montantAnnule,
            'ancien_statut' => $this->ancienStatut,
            'motif'         => $this->motif,
            'cancelled_by'  => $this->cancelledById,
        ];
    }
}

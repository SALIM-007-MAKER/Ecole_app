<?php

namespace App\Modules\Finance\Events;

use Core\Event;

class PaymentRefunded extends Event
{
    public function __construct(
        public readonly int    $paiementId,
        public readonly string $numero,
        public readonly int    $factureId,
        public readonly float  $montantRembourse,
        public readonly string $motif,
        public readonly int    $refundedById,
    ) {
        parent::__construct();
    }

    public function getName(): string
    {
        return 'finance.payment.refunded';
    }

    public function toArray(): array
    {
        return [
            'paiement_id'      => $this->paiementId,
            'numero'           => $this->numero,
            'facture_id'       => $this->factureId,
            'montant_rembourse' => $this->montantRembourse,
            'motif'            => $this->motif,
            'refunded_by'      => $this->refundedById,
        ];
    }
}

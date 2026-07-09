<?php

namespace App\Modules\Finance\Events;

use Core\Event;

class PaymentPartial extends Event
{
    public function __construct(
        public readonly int    $paiementId,
        public readonly string $numero,
        public readonly int    $factureId,
        public readonly float  $montantApplique,
        public readonly float  $montantRestant,
        public readonly int    $completedById,
    ) {
        parent::__construct();
    }

    public function getName(): string
    {
        return 'finance.payment.partial';
    }

    public function toArray(): array
    {
        return [
            'paiement_id'    => $this->paiementId,
            'numero'         => $this->numero,
            'facture_id'     => $this->factureId,
            'montant_applique' => $this->montantApplique,
            'montant_restant'  => $this->montantRestant,
            'completed_by'   => $this->completedById,
        ];
    }
}

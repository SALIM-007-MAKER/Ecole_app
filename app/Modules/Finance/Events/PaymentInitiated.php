<?php

namespace App\Modules\Finance\Events;

use Core\Event;

class PaymentInitiated extends Event
{
    public function __construct(
        public readonly int    $paiementId,
        public readonly string $numero,
        public readonly int    $factureId,
        public readonly float  $montant,
        public readonly string $modePaiement,
        public readonly int    $initiatedById,
    ) {
        parent::__construct();
    }

    public function getName(): string
    {
        return 'finance.payment.initiated';
    }

    public function toArray(): array
    {
        return [
            'paiement_id'   => $this->paiementId,
            'numero'        => $this->numero,
            'facture_id'    => $this->factureId,
            'montant'       => $this->montant,
            'mode_paiement' => $this->modePaiement,
            'initiated_by'  => $this->initiatedById,
        ];
    }
}

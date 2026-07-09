<?php

namespace App\Modules\Finance\Events;

use Core\Event;

class ReceiptGenerated extends Event
{
    public function __construct(
        public readonly int    $recuId,
        public readonly string $numero,
        public readonly int    $paiementId,
        public readonly int    $factureId,
        public readonly int    $eleveId,
        public readonly float  $montant,
        public readonly int    $generatedById,
    ) {
        parent::__construct();
    }

    public function getName(): string
    {
        return 'finance.receipt.generated';
    }

    public function toArray(): array
    {
        return [
            'recu_id'        => $this->recuId,
            'numero'         => $this->numero,
            'paiement_id'    => $this->paiementId,
            'facture_id'     => $this->factureId,
            'eleve_id'       => $this->eleveId,
            'montant'        => $this->montant,
            'generated_by'   => $this->generatedById,
        ];
    }
}

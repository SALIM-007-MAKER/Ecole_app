<?php

namespace App\Modules\Finance\Events;

use Core\Event;

class InvoiceCancelled extends Event
{
    public function __construct(
        public readonly int     $factureId,
        public readonly string  $numero,
        public readonly int     $eleveId,
        public readonly float   $montantTotal,
        public readonly float   $montantPaye,
        public readonly string  $motif,
        public readonly ?int    $avoirId,
        public readonly int     $cancelledById,
    ) {
        parent::__construct();
    }

    public function getName(): string
    {
        return 'finance.invoice.cancelled';
    }

    public function toArray(): array
    {
        return [
            'facture_id'    => $this->factureId,
            'numero'        => $this->numero,
            'eleve_id'      => $this->eleveId,
            'montant_total' => $this->montantTotal,
            'montant_paye'  => $this->montantPaye,
            'motif'         => $this->motif,
            'avoir_id'      => $this->avoirId,
            'cancelled_by'  => $this->cancelledById,
        ];
    }
}

<?php

namespace App\Modules\Finance\Events;

use Core\Event;

class InvoiceCreated extends Event
{
    public function __construct(
        public readonly int    $factureId,
        public readonly string $numero,
        public readonly int    $eleveId,
        public readonly string $anneeScolaire,
        public readonly float  $montantTotal,
        public readonly int    $createdById,
    ) {
        parent::__construct();
    }

    public function getName(): string
    {
        return 'finance.invoice.created';
    }

    public function toArray(): array
    {
        return [
            'facture_id'     => $this->factureId,
            'numero'         => $this->numero,
            'eleve_id'       => $this->eleveId,
            'annee_scolaire' => $this->anneeScolaire,
            'montant_total'  => $this->montantTotal,
            'created_by_id'  => $this->createdById,
        ];
    }
}

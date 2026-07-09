<?php

namespace App\Modules\Finance\Events;

use Core\Event;

class InvoiceGenerated extends Event
{
    public function __construct(
        public readonly int    $nbFactures,
        public readonly string $anneeScolaire,
        public readonly string $contexte,
        public readonly int    $generatedById,
    ) {
        parent::__construct();
    }

    public function getName(): string
    {
        return 'finance.invoice.generated';
    }

    public function toArray(): array
    {
        return [
            'nb_factures'    => $this->nbFactures,
            'annee_scolaire' => $this->anneeScolaire,
            'contexte'       => $this->contexte,
            'generated_by'   => $this->generatedById,
        ];
    }
}

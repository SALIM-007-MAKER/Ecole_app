<?php

namespace App\Modules\Finance\Events;

use Core\Event;

class InvoiceArchived extends Event
{
    public function __construct(
        public readonly int    $factureId,
        public readonly string $numero,
        public readonly string $ancienStatut,
        public readonly int    $archivedById,
    ) {
        parent::__construct();
    }

    public function getName(): string
    {
        return 'finance.invoice.archived';
    }

    public function toArray(): array
    {
        return [
            'facture_id'   => $this->factureId,
            'numero'       => $this->numero,
            'ancien_statut' => $this->ancienStatut,
            'archived_by'  => $this->archivedById,
        ];
    }
}

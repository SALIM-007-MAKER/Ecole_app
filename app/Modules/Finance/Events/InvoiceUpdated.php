<?php

namespace App\Modules\Finance\Events;

use Core\Event;

class InvoiceUpdated extends Event
{
    public function __construct(
        public readonly int    $factureId,
        public readonly string $numero,
        public readonly array  $changedFields,
        public readonly int    $updatedById,
    ) {
        parent::__construct();
    }

    public function getName(): string
    {
        return 'finance.invoice.updated';
    }

    public function toArray(): array
    {
        return [
            'facture_id'    => $this->factureId,
            'numero'        => $this->numero,
            'changed_fields' => $this->changedFields,
            'updated_by'    => $this->updatedById,
        ];
    }
}

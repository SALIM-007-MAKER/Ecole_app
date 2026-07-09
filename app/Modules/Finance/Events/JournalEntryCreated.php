<?php

namespace App\Modules\Finance\Events;

use Core\Event;

class JournalEntryCreated extends Event
{
    public function __construct(
        public readonly int    $ecritureId,
        public readonly string $numero,
        public readonly string $source,
        public readonly string $reference,
        public readonly float  $totalDebit,
        public readonly string $journalCode,
        public readonly int    $createdById,
    ) {
        parent::__construct();
    }

    public function getName(): string
    {
        return 'finance.comptabilite.ecriture.created';
    }

    public function toArray(): array
    {
        return [
            'ecriture_id'  => $this->ecritureId,
            'numero'       => $this->numero,
            'source'       => $this->source,
            'reference'    => $this->reference,
            'total_debit'  => $this->totalDebit,
            'journal_code' => $this->journalCode,
            'created_by'   => $this->createdById,
        ];
    }
}

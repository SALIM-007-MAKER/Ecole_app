<?php

namespace App\Modules\Finance\Events;

use Core\Event;

class CashBalanceUpdated extends Event
{
    public function __construct(
        public readonly int   $sessionId,
        public readonly float $ancienSolde,
        public readonly float $nouveauSolde,
        public readonly int   $updatedById,
    ) {
        parent::__construct();
    }

    public function getName(): string
    {
        return 'finance.caisse.balance.updated';
    }

    public function toArray(): array
    {
        return [
            'session_id'    => $this->sessionId,
            'ancien_solde'  => $this->ancienSolde,
            'nouveau_solde' => $this->nouveauSolde,
            'updated_by'    => $this->updatedById,
        ];
    }
}

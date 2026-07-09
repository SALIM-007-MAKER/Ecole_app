<?php

namespace App\Modules\Finance\Events;

use Core\Event;

class CashRegisterOpened extends Event
{
    public function __construct(
        public readonly int    $sessionId,
        public readonly string $numero,
        public readonly int    $caissierId,
        public readonly float  $soldeInitial,
        public readonly int    $openedById,
    ) {
        parent::__construct();
    }

    public function getName(): string
    {
        return 'finance.caisse.opened';
    }

    public function toArray(): array
    {
        return [
            'session_id'    => $this->sessionId,
            'numero'        => $this->numero,
            'caissier_id'   => $this->caissierId,
            'solde_initial' => $this->soldeInitial,
            'opened_by'     => $this->openedById,
        ];
    }
}

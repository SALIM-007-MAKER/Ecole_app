<?php

namespace App\Modules\Finance\Events;

use Core\Event;

class CashMovementCancelled extends Event
{
    public function __construct(
        public readonly int    $mouvementId,
        public readonly int    $sessionId,
        public readonly float  $montant,
        public readonly string $motif,
        public readonly int    $cancelledById,
    ) {
        parent::__construct();
    }

    public function getName(): string
    {
        return 'finance.caisse.mouvement.cancelled';
    }

    public function toArray(): array
    {
        return [
            'mouvement_id'  => $this->mouvementId,
            'session_id'    => $this->sessionId,
            'montant'       => $this->montant,
            'motif'         => $this->motif,
            'cancelled_by'  => $this->cancelledById,
        ];
    }
}

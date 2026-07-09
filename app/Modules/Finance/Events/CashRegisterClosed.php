<?php

namespace App\Modules\Finance\Events;

use Core\Event;

class CashRegisterClosed extends Event
{
    public function __construct(
        public readonly int    $sessionId,
        public readonly string $numero,
        public readonly int    $caissierId,
        public readonly float  $totalRecettes,
        public readonly float  $totalDecaissements,
        public readonly float  $soldeTheorique,
        public readonly float  $soldeReel,
        public readonly float  $ecart,
        public readonly int    $closedById,
    ) {
        parent::__construct();
    }

    public function getName(): string
    {
        return 'finance.caisse.closed';
    }

    public function toArray(): array
    {
        return [
            'session_id'          => $this->sessionId,
            'numero'              => $this->numero,
            'caissier_id'         => $this->caissierId,
            'total_recettes'      => $this->totalRecettes,
            'total_decaissements' => $this->totalDecaissements,
            'solde_theorique'     => $this->soldeTheorique,
            'solde_reel'          => $this->soldeReel,
            'ecart'               => $this->ecart,
            'closed_by'           => $this->closedById,
        ];
    }
}

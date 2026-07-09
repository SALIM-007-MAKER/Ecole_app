<?php

namespace App\Modules\Finance\Events;

use Core\Event;

class CashMovementCreated extends Event
{
    public function __construct(
        public readonly int    $mouvementId,
        public readonly int    $sessionId,
        public readonly string $type,
        public readonly string $sens,
        public readonly float  $montant,
        public readonly string $libelle,
        public readonly string $source,
        public readonly int    $createdById,
    ) {
        parent::__construct();
    }

    public function getName(): string
    {
        return 'finance.caisse.mouvement.created';
    }

    public function toArray(): array
    {
        return [
            'mouvement_id' => $this->mouvementId,
            'session_id'   => $this->sessionId,
            'type'         => $this->type,
            'sens'         => $this->sens,
            'montant'      => $this->montant,
            'libelle'      => $this->libelle,
            'source'       => $this->source,
            'created_by'   => $this->createdById,
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Bibliotheque\Events;

use Core\Event;

class PenalitePayee extends Event
{
    public function __construct(
        public readonly int    $penaliteId,
        public readonly int    $empruntId,
        public readonly int    $userId,
        public readonly float  $montant,
        public readonly string $mode,
    ) { parent::__construct(); }

    public function toArray(): array
    {
        return [
            'penalite_id' => $this->penaliteId,
            'emprunt_id'  => $this->empruntId,
            'user_id'     => $this->userId,
            'montant'     => $this->montant,
            'mode'        => $this->mode,
        ];
    }
}

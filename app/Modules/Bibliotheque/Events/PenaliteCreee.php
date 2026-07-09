<?php

declare(strict_types=1);

namespace App\Modules\Bibliotheque\Events;

use Core\Event;

class PenaliteCreee extends Event
{
    public function __construct(
        public readonly int    $penaliteId,
        public readonly int    $empruntId,
        public readonly int    $userId,
        public readonly string $type,
        public readonly float  $montant,
        public readonly int    $etablissementId,
    ) { parent::__construct(); }

    public function toArray(): array
    {
        return [
            'penalite_id'      => $this->penaliteId,
            'emprunt_id'       => $this->empruntId,
            'user_id'          => $this->userId,
            'type'             => $this->type,
            'montant'          => $this->montant,
            'etablissement_id' => $this->etablissementId,
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Bibliotheque\Events;

use Core\Event;

class ExemplaireStatutChange extends Event
{
    public function __construct(
        public readonly int    $exemplaireId,
        public readonly int    $ouvrageId,
        public readonly string $ancienStatut,
        public readonly string $nouveauStatut,
        public readonly int    $userId,
    ) { parent::__construct(); }

    public function toArray(): array
    {
        return [
            'exemplaire_id' => $this->exemplaireId,
            'ouvrage_id'    => $this->ouvrageId,
            'ancien_statut' => $this->ancienStatut,
            'nouveau_statut'=> $this->nouveauStatut,
            'user_id'       => $this->userId,
        ];
    }
}

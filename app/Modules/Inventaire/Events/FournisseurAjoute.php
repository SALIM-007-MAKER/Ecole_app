<?php

declare(strict_types=1);

namespace App\Modules\Inventaire\Events;

use Core\Event;

class FournisseurAjoute extends Event
{
    public function __construct(
        public readonly int    $fournisseurId,
        public readonly string $nom,
        public readonly int    $userId,
        public readonly int    $etablissementId,
    ) { parent::__construct(); }

    public function toArray(): array
    {
        return [
            'fournisseur_id'   => $this->fournisseurId,
            'nom'              => $this->nom,
            'user_id'          => $this->userId,
            'etablissement_id' => $this->etablissementId,
        ];
    }
}

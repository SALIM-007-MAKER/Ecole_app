<?php

declare(strict_types=1);

namespace App\Modules\Inventaire\Events;

use Core\Event;

class CommandeCreee extends Event
{
    public function __construct(
        public readonly int    $commandeId,
        public readonly string $numero,
        public readonly int    $fournisseurId,
        public readonly float  $totalHt,
        public readonly int    $userId,
        public readonly int    $etablissementId,
    ) { parent::__construct(); }

    public function toArray(): array
    {
        return [
            'commande_id'      => $this->commandeId,
            'numero'           => $this->numero,
            'fournisseur_id'   => $this->fournisseurId,
            'total_ht'         => $this->totalHt,
            'user_id'          => $this->userId,
            'etablissement_id' => $this->etablissementId,
        ];
    }
}

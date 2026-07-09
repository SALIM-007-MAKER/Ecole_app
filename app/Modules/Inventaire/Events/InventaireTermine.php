<?php

declare(strict_types=1);

namespace App\Modules\Inventaire\Events;

use Core\Event;

class InventaireTermine extends Event
{
    public function __construct(
        public readonly int    $inventaireId,
        public readonly string $nom,
        public readonly int    $nbEcarts,
        public readonly int    $nbAjustements,
        public readonly int    $createdBy,
        public readonly int    $etablissementId,
    ) { parent::__construct(); }

    public function toArray(): array
    {
        return [
            'inventaire_id'    => $this->inventaireId,
            'nom'              => $this->nom,
            'nb_ecarts'        => $this->nbEcarts,
            'nb_ajustements'   => $this->nbAjustements,
            'created_by'       => $this->createdBy,
            'etablissement_id' => $this->etablissementId,
        ];
    }
}

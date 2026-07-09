<?php

declare(strict_types=1);

namespace App\Modules\Bibliotheque\Events;

use Core\Event;

class InventaireTermine extends Event
{
    public function __construct(
        public readonly int    $inventaireId,
        public readonly string $nom,
        public readonly int    $nbScanned,
        public readonly int    $nbManquants,
        public readonly int    $nbDeteriores,
        public readonly int    $createdBy,
        public readonly int    $etablissementId,
    ) { parent::__construct(); }

    public function toArray(): array
    {
        return [
            'inventaire_id'    => $this->inventaireId,
            'nom'              => $this->nom,
            'nb_scanned'       => $this->nbScanned,
            'nb_manquants'     => $this->nbManquants,
            'nb_deteriores'    => $this->nbDeteriores,
            'created_by'       => $this->createdBy,
            'etablissement_id' => $this->etablissementId,
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Bibliotheque\Events;

use Core\Event;

class ExemplaireAjoute extends Event
{
    public function __construct(
        public readonly int    $exemplaireId,
        public readonly int    $ouvrageId,
        public readonly string $numeroInventaire,
        public readonly int    $userId,
        public readonly int    $etablissementId,
    ) { parent::__construct(); }

    public function toArray(): array
    {
        return [
            'exemplaire_id'    => $this->exemplaireId,
            'ouvrage_id'       => $this->ouvrageId,
            'numero_inventaire'=> $this->numeroInventaire,
            'user_id'          => $this->userId,
            'etablissement_id' => $this->etablissementId,
        ];
    }
}

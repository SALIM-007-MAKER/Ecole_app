<?php

declare(strict_types=1);

namespace App\Modules\Bibliotheque\Events;

use Core\Event;

class EmpruntProlonge extends Event
{
    public function __construct(
        public readonly int    $empruntId,
        public readonly int    $userId,
        public readonly string $nouvelleDateRetour,
        public readonly int    $nombreProlongation,
    ) { parent::__construct(); }

    public function toArray(): array
    {
        return [
            'emprunt_id'          => $this->empruntId,
            'user_id'             => $this->userId,
            'nouvelle_date_retour' => $this->nouvelleDateRetour,
            'nombre_prolongation' => $this->nombreProlongation,
        ];
    }
}

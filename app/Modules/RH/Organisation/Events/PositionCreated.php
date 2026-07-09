<?php

declare(strict_types=1);

namespace App\Modules\RH\Organisation\Events;

use Core\Event;

class PositionCreated extends Event
{
    public function __construct(
        public readonly int    $posteId,
        public readonly string $intitule,
        public readonly string $code,
        public readonly string $categorie,
        public readonly ?int   $departementId,
        public readonly int    $createdBy
    ) {}

    public function toArray(): array
    {
        return [
            'poste_id'       => $this->posteId,
            'intitule'       => $this->intitule,
            'code'           => $this->code,
            'categorie'      => $this->categorie,
            'departement_id' => $this->departementId,
            'created_by'     => $this->createdBy,
            'fired_at'       => $this->firedAt(),
        ];
    }
}

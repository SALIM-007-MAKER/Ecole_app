<?php

declare(strict_types=1);

namespace App\Modules\Bibliotheque\DTO;

class PenaliteDTO
{
    public function __construct(
        public readonly int     $empruntId,
        public readonly int     $userId,
        public readonly string  $type,
        public readonly float   $montant,
        public readonly ?int    $joursRetard,
        public readonly ?string $notes,
        public readonly int     $etablissementId,
    ) {}
}

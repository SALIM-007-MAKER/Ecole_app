<?php

declare(strict_types=1);

namespace App\Modules\Bibliotheque\DTO;

class InventaireDTO
{
    public function __construct(
        public readonly string  $nom,
        public readonly ?string $description,
        public readonly string  $dateDebut,
        public readonly ?string $dateFinPrevue,
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            nom:           trim($data['nom'] ?? ''),
            description:   $data['description'] ?? null,
            dateDebut:     $data['date_debut'] ?? date('Y-m-d'),
            dateFinPrevue: $data['date_fin_prevue'] ?? null,
        );
    }
}

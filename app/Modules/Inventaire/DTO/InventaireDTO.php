<?php

declare(strict_types=1);

namespace App\Modules\Inventaire\DTO;

class InventaireDTO
{
    public function __construct(
        public readonly string  $nom,
        public readonly string  $dateDebut,
        public readonly ?string $description,
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            nom:         trim($data['nom'] ?? ''),
            dateDebut:   $data['date_debut'] ?? date('Y-m-d'),
            description: $data['description'] ?? null,
        );
    }
}

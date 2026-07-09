<?php

declare(strict_types=1);

namespace App\Modules\Inventaire\DTO;

class ReceptionDTO
{
    public function __construct(
        public readonly int     $commandeId,
        public readonly string  $dateReception,
        public readonly ?string $bonLivraison,
        public readonly ?string $notes,
        public readonly array   $lignes,
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            commandeId:    (int)($data['commande_id'] ?? 0),
            dateReception: $data['date_reception'] ?? date('Y-m-d'),
            bonLivraison:  $data['bon_livraison'] ?? null,
            notes:         $data['notes'] ?? null,
            lignes:        $data['lignes'] ?? [],
        );
    }
}

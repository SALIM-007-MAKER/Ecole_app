<?php

declare(strict_types=1);

namespace App\Modules\Inventaire\DTO;

class CommandeDTO
{
    public function __construct(
        public readonly int     $fournisseurId,
        public readonly string  $dateCommande,
        public readonly ?string $dateLivraisonPrevue,
        public readonly float   $tvaTaux,
        public readonly ?string $notes,
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            fournisseurId:       (int)($data['fournisseur_id'] ?? 0),
            dateCommande:        $data['date_commande'] ?? date('Y-m-d'),
            dateLivraisonPrevue: $data['date_livraison_prevue'] ?? null,
            tvaTaux:             (float)($data['tva_taux'] ?? 20),
            notes:               $data['notes'] ?? null,
        );
    }
}

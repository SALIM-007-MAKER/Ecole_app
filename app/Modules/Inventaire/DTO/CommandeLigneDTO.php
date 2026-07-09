<?php

declare(strict_types=1);

namespace App\Modules\Inventaire\DTO;

class CommandeLigneDTO
{
    public function __construct(
        public readonly int   $articleId,
        public readonly float $quantiteCommandee,
        public readonly float $prixUnitaireHt,
        public readonly float $tvaTaux,
        public readonly ?string $notes,
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            articleId:         (int)($data['article_id'] ?? 0),
            quantiteCommandee: (float)($data['quantite_commandee'] ?? 0),
            prixUnitaireHt:    (float)($data['prix_unitaire_ht'] ?? 0),
            tvaTaux:           (float)($data['tva_taux'] ?? 20),
            notes:             $data['notes'] ?? null,
        );
    }
}

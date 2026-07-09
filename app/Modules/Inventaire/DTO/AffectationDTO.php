<?php

declare(strict_types=1);

namespace App\Modules\Inventaire\DTO;

class AffectationDTO
{
    public function __construct(
        public readonly int     $articleId,
        public readonly int     $userId,
        public readonly float   $quantite,
        public readonly string  $dateAffectation,
        public readonly ?string $dateRetourPrevue,
        public readonly ?int    $emplacementId,
        public readonly ?string $notes,
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            articleId:        (int)($data['article_id'] ?? 0),
            userId:           (int)($data['user_id'] ?? 0),
            quantite:         (float)($data['quantite'] ?? 1),
            dateAffectation:  $data['date_affectation'] ?? date('Y-m-d'),
            dateRetourPrevue: $data['date_retour_prevue'] ?? null,
            emplacementId:    isset($data['emplacement_id']) ? (int)$data['emplacement_id'] : null,
            notes:            $data['notes'] ?? null,
        );
    }
}

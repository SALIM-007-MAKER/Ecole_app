<?php

declare(strict_types=1);

namespace App\Modules\Bibliotheque\DTO;

class EmpruntDTO
{
    public function __construct(
        public readonly int     $exemplaireId,
        public readonly int     $userId,
        public readonly string  $dateRetourPrevue,
        public readonly ?string $notes,
        public readonly int     $etablissementId,
    ) {}

    public static function fromRequest(array $data, int $etablissementId): self
    {
        return new self(
            exemplaireId:    (int)($data['exemplaire_id'] ?? 0),
            userId:          (int)($data['user_id'] ?? 0),
            dateRetourPrevue:$data['date_retour_prevue'] ?? date('Y-m-d', strtotime('+14 days')),
            notes:           $data['notes'] ?? null,
            etablissementId: $etablissementId,
        );
    }
}

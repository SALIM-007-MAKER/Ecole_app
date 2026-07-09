<?php

declare(strict_types=1);

namespace App\Modules\Bibliotheque\DTO;

class ReservationDTO
{
    public function __construct(
        public readonly int     $ouvrageId,
        public readonly int     $userId,
        public readonly ?string $notes,
        public readonly int     $etablissementId,
    ) {}

    public static function fromRequest(array $data, int $etablissementId): self
    {
        return new self(
            ouvrageId:       (int)($data['ouvrage_id'] ?? 0),
            userId:          (int)($data['user_id'] ?? 0),
            notes:           $data['notes'] ?? null,
            etablissementId: $etablissementId,
        );
    }
}

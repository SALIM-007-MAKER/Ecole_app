<?php

namespace App\Modules\Academique\DTO;

final class AppreciationDTO
{
    public function __construct(
        public readonly int     $eleveId,
        public readonly ?string $texte,
    ) {}

    public static function fromRequest(array $data): self
    {
        $texte = isset($data['texte']) && trim($data['texte']) !== ''
            ? trim($data['texte'])
            : null;

        return new self(
            eleveId: (int)($data['eleve_id'] ?? 0),
            texte:   $texte,
        );
    }

    public function validate(): array
    {
        $errors = [];

        if ($this->eleveId <= 0) {
            $errors['eleve_id'][] = "Élève requis.";
        }
        if ($this->texte !== null && mb_strlen($this->texte) > 255) {
            $errors['texte'][] = "Appréciation trop longue (max 255 caractères).";
        }

        return $errors;
    }
}

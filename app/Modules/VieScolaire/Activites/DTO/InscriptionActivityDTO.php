<?php

namespace App\Modules\VieScolaire\Activites\DTO;

class InscriptionActivityDTO
{
    public function __construct(
        public readonly int     $activiteId,
        public readonly int     $eleveId,
        public readonly ?string $note = null,
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            activiteId: (int)($data['activite_id'] ?? 0),
            eleveId:    (int)($data['eleve_id']    ?? 0),
            note:       trim($data['note'] ?? '') ?: null,
        );
    }

    public function validate(): array
    {
        $errors = [];
        if ($this->activiteId <= 0) $errors[] = 'L\'activité est invalide.';
        if ($this->eleveId    <= 0) $errors[] = 'L\'élève est invalide.';
        return $errors;
    }
}

<?php

namespace App\Modules\Finance\DTO;

class CashRegisterDTO
{
    public function __construct(
        public readonly float   $soldeInitial,
        public readonly ?string $note = null,
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            soldeInitial: (float)($data['solde_initial'] ?? 0),
            note:         trim($data['note'] ?? '') ?: null,
        );
    }

    public function validate(): array
    {
        $errors = [];
        if ($this->soldeInitial < 0) {
            $errors['solde_initial'] = 'Le solde initial ne peut pas être négatif.';
        }
        return $errors;
    }
}

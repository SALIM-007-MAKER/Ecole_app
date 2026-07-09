<?php

declare(strict_types=1);

namespace App\Modules\Communication\DTO;

class GroupeDTO
{
    public function __construct(
        public readonly string  $nom,
        public readonly ?string $description = null,
        public readonly string  $type        = 'manuel',
        public readonly array   $criteres    = [],
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            nom:         trim($data['nom']         ?? ''),
            description: !empty($data['description']) ? trim($data['description']) : null,
            type:        $data['type']             ?? 'manuel',
            criteres:    $data['criteres']         ?? [],
        );
    }

    public function validate(): array
    {
        $errors = [];
        if (empty($this->nom)) {
            $errors[] = 'Le nom du groupe est requis.';
        }
        if (!in_array($this->type, ['manuel', 'role', 'classe', 'niveau', 'etablissement'], true)) {
            $errors[] = 'Type de groupe invalide.';
        }
        return $errors;
    }
}

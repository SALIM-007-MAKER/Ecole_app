<?php

declare(strict_types=1);

namespace App\Modules\Documents\DTO;

class TagDTO
{
    public function __construct(
        public readonly string  $nom,
        public readonly string  $couleur         = 'slate',
        public readonly ?string $moduleSource    = null,
        public readonly int     $etablissementId = 1,
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            nom:             trim($data['nom'] ?? ''),
            couleur:         trim($data['couleur'] ?? 'slate'),
            moduleSource:    ($v = trim($data['module_source'] ?? '')) !== '' ? $v : null,
            etablissementId: (int)($data['etablissement_id'] ?? 1),
        );
    }

    public function validate(): array
    {
        $errors = [];
        if (empty($this->nom)) $errors['nom'] = 'Le nom du tag est requis.';
        elseif (mb_strlen($this->nom) > 50) $errors['nom'] = 'Le nom ne doit pas dépasser 50 caractères.';
        return $errors;
    }
}

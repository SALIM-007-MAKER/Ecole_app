<?php

declare(strict_types=1);

namespace App\Modules\Documents\DTO;

class FolderDTO
{
    public function __construct(
        public readonly string  $nom,
        public readonly string  $moduleSource,
        public readonly ?int    $parentId    = null,
        public readonly ?string $description = null,
        public readonly string  $icone       = 'folder',
        public readonly string  $couleur     = 'slate',
        public readonly int     $ordre       = 0,
        public readonly int     $etablissementId = 1,
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            nom:             trim($data['nom'] ?? ''),
            moduleSource:    trim($data['module_source'] ?? ''),
            parentId:        ($v = ($data['parent_id'] ?? '')) !== '' ? (int)$v : null,
            description:     ($v = trim($data['description'] ?? '')) !== '' ? $v : null,
            icone:           trim($data['icone'] ?? 'folder'),
            couleur:         trim($data['couleur'] ?? 'slate'),
            ordre:           (int)($data['ordre'] ?? 0),
            etablissementId: (int)($data['etablissement_id'] ?? 1),
        );
    }

    public function validate(): array
    {
        $errors = [];
        if (empty($this->nom)) {
            $errors['nom'] = 'Le nom du dossier est requis.';
        } elseif (mb_strlen($this->nom) > 200) {
            $errors['nom'] = 'Le nom ne doit pas dépasser 200 caractères.';
        }
        if (empty($this->moduleSource)) {
            $errors['module_source'] = 'Le module source est requis.';
        }
        return $errors;
    }
}

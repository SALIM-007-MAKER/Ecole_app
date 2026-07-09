<?php

namespace App\Modules\Finance\DTO;

class CategorieFraisDTO
{
    public const COULEURS = [
        '#6366f1' => 'Violet',
        '#3b82f6' => 'Bleu',
        '#10b981' => 'Vert',
        '#f59e0b' => 'Ambre',
        '#ef4444' => 'Rouge',
        '#ec4899' => 'Rose',
        '#8b5cf6' => 'Pourpre',
        '#14b8a6' => 'Teal',
        '#f97316' => 'Orange',
        '#64748b' => 'Slate',
    ];

    public const ICONES = [
        'book-open'       => 'Scolaire',
        'bus'             => 'Transport',
        'utensils'        => 'Cantine',
        'activity'        => 'Activités',
        'file-text'       => 'Documents',
        'file'            => 'Administratif',
        'users'           => 'Social',
        'home'            => 'Hébergement',
        'cpu'             => 'Informatique',
        'music'           => 'Arts',
        'globe'           => 'International',
        'dollar-sign'     => 'Financier',
        'currency-dollar' => 'Général',
    ];

    public function __construct(
        public readonly string $code,
        public readonly string $nom,
        public readonly string $description = '',
        public readonly string $couleur     = '#6366f1',
        public readonly string $icone       = 'currency-dollar',
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            code:        strtoupper(trim($data['code'] ?? '')),
            nom:         trim($data['nom'] ?? ''),
            description: trim($data['description'] ?? ''),
            couleur:     trim($data['couleur'] ?? '#6366f1'),
            icone:       trim($data['icone'] ?? 'currency-dollar'),
        );
    }

    public function toArray(): array
    {
        return [
            'code'        => $this->code,
            'nom'         => $this->nom,
            'description' => $this->description ?: null,
            'couleur'     => $this->couleur,
            'icone'       => $this->icone,
        ];
    }

    public function validate(): array
    {
        $errors = [];

        if ($this->code === '') {
            $errors['code'] = 'Le code est obligatoire.';
        } elseif (!preg_match('/^[A-Z0-9_]{2,20}$/', $this->code)) {
            $errors['code'] = 'Le code doit contenir uniquement des lettres majuscules, chiffres et underscores (2-20 caractères).';
        }

        if ($this->nom === '') {
            $errors['nom'] = 'Le nom de la catégorie est obligatoire.';
        } elseif (mb_strlen($this->nom) > 100) {
            $errors['nom'] = 'Le nom ne peut pas dépasser 100 caractères.';
        }

        if ($this->couleur !== '' && !preg_match('/^#[0-9A-Fa-f]{6}$/', $this->couleur)) {
            $errors['couleur'] = 'La couleur doit être un code hexadécimal valide (ex. #6366f1).';
        }

        return $errors;
    }
}

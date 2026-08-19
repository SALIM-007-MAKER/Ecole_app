<?php

namespace App\Modules\Finance\DTO;

class CategorieDepenseDTO
{
    public const COULEURS = [
        '#4e73df' => 'Bleu',
        '#1cc88a' => 'Vert',
        '#36b9cc' => 'Cyan',
        '#f6c23e' => 'Jaune',
        '#e74a3b' => 'Rouge',
        '#858796' => 'Gris',
        '#6366f1' => 'Violet',
    ];

    public const ICONES = [
        'users'            => 'Salaires',
        'package'          => 'Fournitures',
        'building'         => 'Infrastructure',
        'plug'             => 'Services',
        'graduation-cap'   => 'Pédagogie',
        'more-horizontal'  => 'Divers',
        'receipt'          => 'Général',
    ];

    public function __construct(
        public readonly string $code,
        public readonly string $nom,
        public readonly string $description = '',
        public readonly string $couleur     = '#64748b',
        public readonly string $icone       = 'receipt',
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            code:        strtoupper(trim($data['code'] ?? '')),
            nom:         trim($data['nom'] ?? ''),
            description: trim($data['description'] ?? ''),
            couleur:     trim($data['couleur'] ?? '#64748b'),
            icone:       trim($data['icone'] ?? 'receipt'),
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
            $errors['couleur'] = 'La couleur doit être un code hexadécimal valide (ex. #64748b).';
        }

        return $errors;
    }
}

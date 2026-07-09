<?php

declare(strict_types=1);

namespace App\Modules\RH\Contrats\DTO;

class AvenantDTO
{
    public const TYPES = ['salaire', 'poste', 'duree', 'horaire', 'teletravail', 'autre'];

    public function __construct(
        public readonly string  $type,
        public readonly string  $objet,
        public readonly ?string $description,
        public readonly string  $dateEffet,
        public readonly ?array  $ancienneValeur,
        public readonly ?array  $nouvelleValeur
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            type:           trim($data['type'] ?? 'autre'),
            objet:          trim($data['objet'] ?? ''),
            description:    ($data['description'] ?? '') !== '' ? trim($data['description']) : null,
            dateEffet:      trim($data['date_effet'] ?? ''),
            ancienneValeur: isset($data['ancienne_valeur']) && $data['ancienne_valeur'] !== ''
                            ? ['valeur' => trim($data['ancienne_valeur'])] : null,
            nouvelleValeur: isset($data['nouvelle_valeur']) && $data['nouvelle_valeur'] !== ''
                            ? ['valeur' => trim($data['nouvelle_valeur'])] : null,
        );
    }

    public function validate(): array
    {
        $errors = [];
        if (!in_array($this->type, self::TYPES, true)) {
            $errors['type'] = 'Type d\'avenant invalide.';
        }
        if ($this->objet === '') {
            $errors['objet'] = 'L\'objet de l\'avenant est obligatoire.';
        } elseif (mb_strlen($this->objet) > 200) {
            $errors['objet'] = 'L\'objet ne peut pas dépasser 200 caractères.';
        }
        if ($this->dateEffet === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->dateEffet)) {
            $errors['date_effet'] = 'La date d\'effet est obligatoire.';
        }
        return $errors;
    }
}

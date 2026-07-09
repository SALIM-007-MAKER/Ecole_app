<?php

namespace App\Modules\Scolarite\DTO;

class ClasseDTO
{
    public function __construct(
        public readonly string $nom,
        public readonly string $niveau,
        public readonly string $anneeScolaire,
        public readonly int    $maxEleves     = 40,
        public readonly string $description   = '',
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            nom:           strtoupper(trim($data['nom'] ?? '')),
            niveau:        trim($data['niveau'] ?? ''),
            anneeScolaire: trim($data['annee_scolaire'] ?? ''),
            maxEleves:     (int)($data['max_eleves'] ?? 40),
            description:   trim($data['description'] ?? ''),
        );
    }

    public function toArray(): array
    {
        return [
            'nom'           => $this->nom,
            'niveau'        => $this->niveau,
            'annee_scolaire'=> $this->anneeScolaire,
            'max_eleves'    => $this->maxEleves,
            'description'   => $this->description,
        ];
    }

    public function validate(): array
    {
        $errors = [];

        if ($this->nom === '') {
            $errors['nom'][] = 'Le nom de la classe est obligatoire.';
        } elseif (mb_strlen($this->nom) > 10) {
            $errors['nom'][] = 'Le nom ne peut pas dépasser 10 caractères.';
        }

        if ($this->niveau === '') {
            $errors['niveau'][] = 'Le niveau est obligatoire.';
        }

        if ($this->anneeScolaire === '') {
            $errors['annee_scolaire'][] = "L'année scolaire est obligatoire.";
        } elseif (!preg_match('/^\d{4}-\d{4}$/', $this->anneeScolaire)) {
            $errors['annee_scolaire'][] = "Format attendu : 2024-2025.";
        }

        if ($this->maxEleves < 1 || $this->maxEleves > 100) {
            $errors['max_eleves'][] = 'La capacité doit être comprise entre 1 et 100.';
        }

        return $errors;
    }
}

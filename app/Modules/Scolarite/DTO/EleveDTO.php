<?php

namespace App\Modules\Scolarite\DTO;

class EleveDTO
{
    public function __construct(
        public readonly string $nom,
        public readonly string $prenom,
        public readonly string $sexe,
        public readonly string $dateNaissance,
        public readonly string $matricule,
        public readonly ?int   $classeId,
        public readonly ?int   $parentId,
        public readonly string $telephone = '',
        public readonly string $email     = '',
        public readonly string $adresse   = '',
        public readonly int    $actif     = 1,
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            nom:           strtoupper(trim($data['nom'] ?? '')),
            prenom:        trim($data['prenom'] ?? ''),
            sexe:          $data['sexe'] ?? '',
            dateNaissance: $data['date_naissance'] ?? '',
            matricule:     strtoupper(trim($data['matricule'] ?? '')),
            classeId:      !empty($data['classe_id']) ? (int)$data['classe_id'] : null,
            parentId:      !empty($data['parent_id']) ? (int)$data['parent_id'] : null,
            telephone:     trim($data['telephone'] ?? ''),
            email:         strtolower(trim($data['email'] ?? '')),
            adresse:       trim($data['adresse'] ?? ''),
            actif:         isset($data['actif']) ? (int)$data['actif'] : 0,
        );
    }

    public function toArray(): array
    {
        return [
            'nom'            => $this->nom,
            'prenom'         => $this->prenom,
            'sexe'           => $this->sexe,
            'date_naissance' => $this->dateNaissance,
            'matricule'      => $this->matricule,
            'classe_id'      => $this->classeId,
            'parent_id'      => $this->parentId,
            'telephone'      => $this->telephone,
            'email'          => $this->email,
            'adresse'        => $this->adresse,
            'actif'          => $this->actif,
        ];
    }

    public function validate(): array
    {
        $errors = [];

        if ($this->nom === '') {
            $errors['nom'][] = 'Le nom est obligatoire.';
        } elseif (strlen($this->nom) > 100) {
            $errors['nom'][] = 'Le nom ne doit pas dépasser 100 caractères.';
        }

        if ($this->prenom === '') {
            $errors['prenom'][] = 'Le prénom est obligatoire.';
        } elseif (strlen($this->prenom) > 100) {
            $errors['prenom'][] = 'Le prénom ne doit pas dépasser 100 caractères.';
        }

        if (!in_array($this->sexe, ['M', 'F'], true)) {
            $errors['sexe'][] = 'Le sexe doit être M ou F.';
        }

        if ($this->dateNaissance === '') {
            $errors['date_naissance'][] = 'La date de naissance est obligatoire.';
        } elseif (!strtotime($this->dateNaissance)) {
            $errors['date_naissance'][] = 'La date de naissance est invalide.';
        }

        if ($this->matricule === '') {
            $errors['matricule'][] = 'Le matricule est obligatoire.';
        }

        if ($this->email !== '' && !filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'][] = "L'adresse email est invalide.";
        }

        return $errors;
    }
}

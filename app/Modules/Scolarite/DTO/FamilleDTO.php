<?php

namespace App\Modules\Scolarite\DTO;

class FamilleDTO
{
    public function __construct(
        public readonly string $nom,
        public readonly string $adresse                  = '',
        public readonly string $codePostal               = '',
        public readonly string $ville                    = '',
        public readonly string $telephone                = '',
        public readonly string $email                    = '',
        public readonly string $contactUrgenceNom        = '',
        public readonly string $contactUrgenceTelephone  = '',
        public readonly string $contactUrgenceLien       = '',
        public readonly string $notes                    = '',
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            nom:                     strtoupper(trim($data['nom'] ?? '')),
            adresse:                 trim($data['adresse'] ?? ''),
            codePostal:              trim($data['code_postal'] ?? ''),
            ville:                   trim($data['ville'] ?? ''),
            telephone:               trim($data['telephone'] ?? ''),
            email:                   strtolower(trim($data['email'] ?? '')),
            contactUrgenceNom:       trim($data['contact_urgence_nom'] ?? ''),
            contactUrgenceTelephone: trim($data['contact_urgence_telephone'] ?? ''),
            contactUrgenceLien:      trim($data['contact_urgence_lien'] ?? ''),
            notes:                   trim($data['notes'] ?? ''),
        );
    }

    public function toArray(): array
    {
        return [
            'nom'                       => $this->nom,
            'adresse'                   => $this->adresse ?: null,
            'code_postal'               => $this->codePostal ?: null,
            'ville'                     => $this->ville ?: null,
            'telephone'                 => $this->telephone ?: null,
            'email'                     => $this->email ?: null,
            'contact_urgence_nom'       => $this->contactUrgenceNom ?: null,
            'contact_urgence_telephone' => $this->contactUrgenceTelephone ?: null,
            'contact_urgence_lien'      => $this->contactUrgenceLien ?: null,
            'notes'                     => $this->notes ?: null,
        ];
    }

    public function validate(): array
    {
        $errors = [];

        if ($this->nom === '') {
            $errors['nom'] = 'Le nom de la famille est obligatoire.';
        } elseif (mb_strlen($this->nom) > 100) {
            $errors['nom'] = 'Le nom ne peut pas dépasser 100 caractères.';
        }

        if ($this->email !== '' && !filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'L\'adresse e-mail est invalide.';
        }

        if ($this->telephone !== '' && !preg_match('/^[0-9\s\+\-\(\)\.]{6,20}$/', $this->telephone)) {
            $errors['telephone'] = 'Le numéro de téléphone est invalide.';
        }

        if ($this->contactUrgenceTelephone !== '' && !preg_match('/^[0-9\s\+\-\(\)\.]{6,20}$/', $this->contactUrgenceTelephone)) {
            $errors['contact_urgence_telephone'] = 'Le numéro de contact d\'urgence est invalide.';
        }

        return $errors;
    }
}

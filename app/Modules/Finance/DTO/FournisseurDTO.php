<?php

namespace App\Modules\Finance\DTO;

class FournisseurDTO
{
    public function __construct(
        public readonly string  $code,
        public readonly string  $nom,
        public readonly ?string $contact   = null,
        public readonly ?string $telephone = null,
        public readonly ?string $email     = null,
        public readonly ?string $adresse   = null,
        public readonly ?string $iban      = null,
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            code:      strtoupper(trim($data['code'] ?? '')),
            nom:       trim($data['nom'] ?? ''),
            contact:   !empty($data['contact'])   ? trim($data['contact'])   : null,
            telephone: !empty($data['telephone']) ? trim($data['telephone']) : null,
            email:     !empty($data['email'])     ? trim($data['email'])     : null,
            adresse:   !empty($data['adresse'])   ? trim($data['adresse'])   : null,
            iban:      !empty($data['iban'])      ? trim($data['iban'])      : null,
        );
    }

    public function toArray(): array
    {
        return [
            'code'      => $this->code,
            'nom'       => $this->nom,
            'contact'   => $this->contact,
            'telephone' => $this->telephone,
            'email'     => $this->email,
            'adresse'   => $this->adresse,
            'iban'      => $this->iban,
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
            $errors['nom'] = 'Le nom du fournisseur est obligatoire.';
        } elseif (mb_strlen($this->nom) > 150) {
            $errors['nom'] = 'Le nom ne peut pas dépasser 150 caractères.';
        }

        if ($this->email !== null && !filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Adresse e-mail invalide.';
        }

        return $errors;
    }
}

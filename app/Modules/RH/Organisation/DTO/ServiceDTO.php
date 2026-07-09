<?php

declare(strict_types=1);

namespace App\Modules\RH\Organisation\DTO;

class ServiceDTO
{
    public function __construct(
        public readonly int     $departementId,
        public readonly string  $nom,
        public readonly string  $code,
        public readonly ?string $description,
        public readonly ?int    $responsableId,
        public readonly int     $ordreAffichage,
        public readonly bool    $actif
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            departementId:   (int)($data['departement_id'] ?? 0),
            nom:             trim($data['nom'] ?? ''),
            code:            strtoupper(trim($data['code'] ?? '')),
            description:     ($data['description'] ?? '') !== '' ? trim($data['description']) : null,
            responsableId:   ($data['responsable_id'] ?? 0) > 0 ? (int)$data['responsable_id'] : null,
            ordreAffichage:  (int)($data['ordre_affichage'] ?? 0),
            actif:           isset($data['actif']) && (bool)$data['actif']
        );
    }

    public function validate(): array
    {
        $errors = [];
        if ($this->departementId <= 0) {
            $errors['departement_id'] = 'Le département est obligatoire.';
        }
        if ($this->nom === '') {
            $errors['nom'] = 'Le nom du service est obligatoire.';
        } elseif (mb_strlen($this->nom) > 120) {
            $errors['nom'] = 'Le nom ne peut pas dépasser 120 caractères.';
        }
        if ($this->code === '') {
            $errors['code'] = 'Le code est obligatoire.';
        } elseif (!preg_match('/^[A-Z0-9_]{2,30}$/', $this->code)) {
            $errors['code'] = 'Le code doit être en majuscules, 2-30 caractères (A-Z, 0-9, _).';
        }
        return $errors;
    }

    public function toArray(): array
    {
        return [
            'departement_id'  => $this->departementId,
            'nom'             => $this->nom,
            'code'            => $this->code,
            'description'     => $this->description,
            'responsable_id'  => $this->responsableId,
            'ordre_affichage' => $this->ordreAffichage,
            'actif'           => $this->actif ? 1 : 0,
        ];
    }
}

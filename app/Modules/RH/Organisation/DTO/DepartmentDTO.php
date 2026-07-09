<?php

declare(strict_types=1);

namespace App\Modules\RH\Organisation\DTO;

class DepartmentDTO
{
    public function __construct(
        public readonly string  $nom,
        public readonly string  $code,
        public readonly ?string $description,
        public readonly ?int    $responsableId,
        public readonly ?int    $parentId,
        public readonly ?string $budgetCentre,
        public readonly int     $ordreAffichage,
        public readonly bool    $actif
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            nom:             trim($data['nom'] ?? ''),
            code:            strtoupper(trim($data['code'] ?? '')),
            description:     ($data['description'] ?? '') !== '' ? trim($data['description']) : null,
            responsableId:   ($data['responsable_id'] ?? 0) > 0 ? (int)$data['responsable_id'] : null,
            parentId:        ($data['parent_id'] ?? 0) > 0 ? (int)$data['parent_id'] : null,
            budgetCentre:    ($data['budget_centre'] ?? '') !== '' ? trim($data['budget_centre']) : null,
            ordreAffichage:  (int)($data['ordre_affichage'] ?? 0),
            actif:           isset($data['actif']) && (bool)$data['actif']
        );
    }

    public function validate(): array
    {
        $errors = [];
        if ($this->nom === '') {
            $errors['nom'] = 'Le nom du département est obligatoire.';
        } elseif (mb_strlen($this->nom) > 100) {
            $errors['nom'] = 'Le nom ne peut pas dépasser 100 caractères.';
        }
        if ($this->code === '') {
            $errors['code'] = 'Le code est obligatoire.';
        } elseif (!preg_match('/^[A-Z0-9_]{2,20}$/', $this->code)) {
            $errors['code'] = 'Le code doit être en majuscules, 2-20 caractères (A-Z, 0-9, _).';
        }
        if ($this->budgetCentre !== null && mb_strlen($this->budgetCentre) > 30) {
            $errors['budget_centre'] = 'Le code centre de coût ne peut pas dépasser 30 caractères.';
        }
        return $errors;
    }

    public function toArray(): array
    {
        return [
            'nom'              => $this->nom,
            'code'             => $this->code,
            'description'      => $this->description,
            'responsable_id'   => $this->responsableId,
            'parent_id'        => $this->parentId,
            'budget_centre'    => $this->budgetCentre,
            'ordre_affichage'  => $this->ordreAffichage,
            'actif'            => $this->actif ? 1 : 0,
        ];
    }
}

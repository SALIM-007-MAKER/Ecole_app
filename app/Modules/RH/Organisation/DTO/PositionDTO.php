<?php

declare(strict_types=1);

namespace App\Modules\RH\Organisation\DTO;

class PositionDTO
{
    public const CATEGORIES = ['enseignant', 'administratif', 'support', 'direction', 'technique'];

    public function __construct(
        public readonly string  $intitule,
        public readonly string  $code,
        public readonly ?int    $departementId,
        public readonly ?int    $serviceId,
        public readonly string  $categorie,
        public readonly int     $niveau,
        public readonly int     $nbOccupantsMax,
        public readonly ?string $description,
        public readonly bool    $actif
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            intitule:       trim($data['intitule'] ?? ''),
            code:           strtoupper(trim($data['code'] ?? '')),
            departementId:  ($data['departement_id'] ?? 0) > 0 ? (int)$data['departement_id'] : null,
            serviceId:      ($data['service_id'] ?? 0) > 0 ? (int)$data['service_id'] : null,
            categorie:      trim($data['categorie'] ?? ''),
            niveau:         max(1, min(4, (int)($data['niveau'] ?? 1))),
            nbOccupantsMax: max(1, (int)($data['nb_occupants_max'] ?? 1)),
            description:    ($data['description'] ?? '') !== '' ? trim($data['description']) : null,
            actif:          isset($data['actif']) && (bool)$data['actif']
        );
    }

    public function validate(): array
    {
        $errors = [];
        if ($this->intitule === '') {
            $errors['intitule'] = 'L\'intitulé du poste est obligatoire.';
        } elseif (mb_strlen($this->intitule) > 150) {
            $errors['intitule'] = 'L\'intitulé ne peut pas dépasser 150 caractères.';
        }
        if ($this->code === '') {
            $errors['code'] = 'Le code est obligatoire.';
        } elseif (!preg_match('/^[A-Z0-9_]{2,30}$/', $this->code)) {
            $errors['code'] = 'Le code doit être en majuscules, 2-30 caractères (A-Z, 0-9, _).';
        }
        if (!in_array($this->categorie, self::CATEGORIES, true)) {
            $errors['categorie'] = 'Catégorie invalide.';
        }
        if ($this->niveau < 1 || $this->niveau > 4) {
            $errors['niveau'] = 'Le niveau doit être compris entre 1 et 4.';
        }
        if ($this->nbOccupantsMax < 1 || $this->nbOccupantsMax > 50) {
            $errors['nb_occupants_max'] = 'Le nombre max d\'occupants doit être entre 1 et 50.';
        }
        return $errors;
    }

    public function toArray(): array
    {
        return [
            'intitule'        => $this->intitule,
            'code'            => $this->code,
            'departement_id'  => $this->departementId,
            'service_id'      => $this->serviceId,
            'categorie'       => $this->categorie,
            'niveau'          => $this->niveau,
            'nb_occupants_max'=> $this->nbOccupantsMax,
            'description'     => $this->description,
            'actif'           => $this->actif ? 1 : 0,
        ];
    }
}

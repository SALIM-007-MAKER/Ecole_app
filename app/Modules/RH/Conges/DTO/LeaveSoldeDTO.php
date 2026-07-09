<?php

declare(strict_types=1);

namespace App\Modules\RH\Conges\DTO;

class LeaveSoldeDTO
{
    public function __construct(
        public readonly int   $employeId,
        public readonly int   $typeCongeId,
        public readonly int   $annee,
        public readonly float $soldeInitial,
    ) {}

    public static function fromRequest(array $post): self
    {
        return new self(
            employeId:    (int)($post['employe_id']    ?? 0),
            typeCongeId:  (int)($post['type_conge_id'] ?? 0),
            annee:        (int)($post['annee']         ?? date('Y')),
            soldeInitial: max(0.0, (float)($post['solde_initial'] ?? 0)),
        );
    }

    public function validate(): array
    {
        $errors = [];
        if ($this->employeId <= 0)   $errors['employe_id']    = "L'employé est obligatoire.";
        if ($this->typeCongeId <= 0) $errors['type_conge_id'] = 'Le type de congé est obligatoire.';
        if ($this->annee < 2000 || $this->annee > 2100)
                                     $errors['annee']         = 'Année invalide.';
        if ($this->soldeInitial < 0) $errors['solde_initial'] = 'Le solde initial ne peut pas être négatif.';
        return $errors;
    }
}

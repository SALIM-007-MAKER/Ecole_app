<?php

namespace App\Modules\RH\Employes\Events;

use Core\Event;

class EmployeeCreated extends Event
{
    public function __construct(
        public readonly int    $employeId,
        public readonly string $matricule,
        public readonly string $typePersonnel,
        public readonly string $nom,
        public readonly string $prenom,
        public readonly int    $creeParId,
    ) {
        parent::__construct();
    }

    public function toArray(): array
    {
        return [
            'employe_id'    => $this->employeId,
            'matricule'     => $this->matricule,
            'type_personnel'=> $this->typePersonnel,
            'nom'           => $this->nom,
            'prenom'        => $this->prenom,
            'cree_par_id'   => $this->creeParId,
        ];
    }
}

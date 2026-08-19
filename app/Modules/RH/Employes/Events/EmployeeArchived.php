<?php

namespace App\Modules\RH\Employes\Events;

use Core\Event;

class EmployeeArchived extends Event
{
    public function __construct(
        public readonly int    $employeId,
        public readonly string $matricule,
        public readonly string $nom,
        public readonly string $prenom,
        public readonly int    $archiveParId,
    ) {
        parent::__construct();
    }

    public function toArray(): array
    {
        return [
            'employe_id'     => $this->employeId,
            'matricule'      => $this->matricule,
            'nom'            => $this->nom,
            'prenom'         => $this->prenom,
            'archive_par_id' => $this->archiveParId,
        ];
    }
}

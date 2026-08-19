<?php

namespace App\Modules\RH\Employes\Events;

use Core\Event;

class EmployeeRestored extends Event
{
    public function __construct(
        public readonly int    $employeId,
        public readonly string $matricule,
        public readonly int    $restaureParId,
    ) {
        parent::__construct();
    }

    public function toArray(): array
    {
        return [
            'employe_id'      => $this->employeId,
            'matricule'       => $this->matricule,
            'restaure_par_id' => $this->restaureParId,
        ];
    }
}

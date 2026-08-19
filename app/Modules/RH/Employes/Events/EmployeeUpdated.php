<?php

namespace App\Modules\RH\Employes\Events;

use Core\Event;

class EmployeeUpdated extends Event
{
    public function __construct(
        public readonly int    $employeId,
        public readonly string $matricule,
        public readonly array  $changes,
        public readonly int    $modifieParId,
    ) {
        parent::__construct();
    }

    public function toArray(): array
    {
        return [
            'employe_id'    => $this->employeId,
            'matricule'     => $this->matricule,
            'changes'       => $this->changes,
            'modifie_par_id'=> $this->modifieParId,
        ];
    }
}

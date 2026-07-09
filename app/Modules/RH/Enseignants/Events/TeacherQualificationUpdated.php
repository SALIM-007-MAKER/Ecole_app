<?php

namespace App\Modules\RH\Enseignants\Events;

use Core\Event;

class TeacherQualificationUpdated extends Event
{
    public function __construct(
        public readonly int    $enseignantId,
        public readonly string $matricule,
        public readonly string $typeQualification,
        public readonly string $intitule,
        public readonly int    $modifieParId,
    ) {}

    public function toArray(): array
    {
        return [
            'enseignant_id'     => $this->enseignantId,
            'matricule'         => $this->matricule,
            'type_qualification'=> $this->typeQualification,
            'intitule'          => $this->intitule,
            'modifie_par_id'    => $this->modifieParId,
            'fired_at'          => $this->firedAt(),
        ];
    }
}

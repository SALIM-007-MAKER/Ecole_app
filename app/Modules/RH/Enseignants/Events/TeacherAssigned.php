<?php

namespace App\Modules\RH\Enseignants\Events;

use Core\Event;

class TeacherAssigned extends Event
{
    public function __construct(
        public readonly int    $enseignantId,
        public readonly string $matricule,
        public readonly array  $matieres,
        public readonly int    $assigneParId,
    ) {
        parent::__construct();
    }

    public function toArray(): array
    {
        return [
            'enseignant_id'  => $this->enseignantId,
            'matricule'      => $this->matricule,
            'matieres'       => $this->matieres,
            'assigne_par_id' => $this->assigneParId,
            'fired_at'       => $this->firedAt(),
        ];
    }
}

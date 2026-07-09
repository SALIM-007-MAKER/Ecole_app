<?php

namespace App\Modules\RH\Enseignants\Events;

use Core\Event;

class TeacherUpdated extends Event
{
    public function __construct(
        public readonly int    $enseignantId,
        public readonly string $matricule,
        public readonly array  $changes,
        public readonly int    $modifieParId,
    ) {}

    public function toArray(): array
    {
        return [
            'enseignant_id'  => $this->enseignantId,
            'matricule'      => $this->matricule,
            'changes'        => $this->changes,
            'modifie_par_id' => $this->modifieParId,
            'fired_at'       => $this->firedAt(),
        ];
    }
}

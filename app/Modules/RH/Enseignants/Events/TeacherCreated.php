<?php

namespace App\Modules\RH\Enseignants\Events;

use Core\Event;

class TeacherCreated extends Event
{
    public function __construct(
        public readonly int    $enseignantId,
        public readonly int    $employeId,
        public readonly string $matricule,
        public readonly string $nom,
        public readonly string $prenom,
        public readonly string $statutPedagogique,
        public readonly int    $creeParId,
    ) {
        parent::__construct();
    }

    public function toArray(): array
    {
        return [
            'enseignant_id'      => $this->enseignantId,
            'employe_id'         => $this->employeId,
            'matricule'          => $this->matricule,
            'nom'                => $this->nom,
            'prenom'             => $this->prenom,
            'statut_pedagogique' => $this->statutPedagogique,
            'cree_par_id'        => $this->creeParId,
            'fired_at'           => $this->firedAt(),
        ];
    }
}

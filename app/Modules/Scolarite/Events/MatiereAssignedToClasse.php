<?php

namespace App\Modules\Scolarite\Events;

use Core\Event;

class MatiereAssignedToClasse extends Event
{
    public function __construct(
        public readonly int    $matiereId,
        public readonly int    $classeId,
        public readonly int    $professeurId,
        public readonly string $anneeScolaire,
        public readonly int    $assignedById,
    ) {}

    public function getName(): string { return 'matiere.assigned_to_classe'; }

    public function toArray(): array
    {
        return [
            'matiere_id'    => $this->matiereId,
            'classe_id'     => $this->classeId,
            'professeur_id' => $this->professeurId,
            'annee_scolaire'=> $this->anneeScolaire,
            'assigned_by'   => $this->assignedById,
            'fired_at'      => $this->getFiredAt(),
        ];
    }
}

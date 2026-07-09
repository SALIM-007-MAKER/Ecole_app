<?php

namespace App\Modules\Scolarite\Events;

use Core\Event;

/**
 * Événement V2 — Enseignant affecté à une classe/matière.
 *
 * Dispatché par AffectationService::affecterEnseignant().
 * Déclencheurs : mise à jour emploi du temps, notification enseignant.
 */
class ClasseAffectee extends Event
{
    public function __construct(
        public readonly int $classeId,
        public readonly int $professeurId,
        public readonly int $matiereId,
        public readonly int $affecteParId,
    ) {
        parent::__construct();
    }

    public function toArray(): array
    {
        return [
            'classe_id'     => $this->classeId,
            'professeur_id' => $this->professeurId,
            'matiere_id'    => $this->matiereId,
        ];
    }
}

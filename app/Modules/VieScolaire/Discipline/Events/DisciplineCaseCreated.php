<?php

namespace App\Modules\VieScolaire\Discipline\Events;

use Core\Event;

/**
 * Déclenché à chaque nouvel incident disciplinaire signalé.
 * Le dossier peut être nouveau (premier incident) ou existant.
 */
class DisciplineCaseCreated extends Event
{
    public function __construct(
        public readonly int    $incidentId,
        public readonly int    $dossierId,
        public readonly int    $eleveId,
        public readonly int    $classeId,
        public readonly string $gravite,
        public readonly string $categorieCode,
        public readonly string $anneeScolaire,
        public readonly int    $signaleParId,
        public readonly bool   $premierIncident,
    ) {}
}

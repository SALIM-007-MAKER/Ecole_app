<?php

namespace App\Modules\VieScolaire\Discipline\Events;

use Core\Event;

/**
 * Déclenché quand une sanction est prononcée sur un dossier disciplinaire.
 */
class DisciplinaryActionAssigned extends Event
{
    public function __construct(
        public readonly int    $sanctionId,
        public readonly int    $dossierId,
        public readonly int    $eleveId,
        public readonly string $typeSanction,
        public readonly string $dateSanction,
        public readonly string $anneeScolaire,
        public readonly int    $prononceParId,
    ) {}
}

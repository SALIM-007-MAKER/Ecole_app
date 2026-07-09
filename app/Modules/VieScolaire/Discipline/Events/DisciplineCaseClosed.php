<?php

namespace App\Modules\VieScolaire\Discipline\Events;

use Core\Event;

class DisciplineCaseClosed extends Event
{
    public function __construct(
        public readonly int    $dossierId,
        public readonly int    $eleveId,
        public readonly int    $classeId,
        public readonly string $anneeScolaire,
        public readonly int    $nbIncidentsTotal,
        public readonly int    $closParId,
    ) {}
}

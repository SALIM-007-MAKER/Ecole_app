<?php

namespace App\Modules\VieScolaire\Discipline\Events;

use Core\Event;

class DisciplineAppealSubmitted extends Event
{
    public function __construct(
        public readonly int    $appelId,
        public readonly int    $sanctionId,
        public readonly int    $dossierId,
        public readonly int    $eleveId,
        public readonly string $anneeScolaire,
        public readonly int    $deposePar,
    ) {}
}

<?php

namespace App\Modules\VieScolaire\EmploisDuTemps\Events;

use Core\Event;

class TimetableConflictDetected extends Event
{
    public function __construct(
        public readonly string $typeConflit,
        public readonly int    $edtId,
        public readonly int    $classeId,
        public readonly string $anneeScolaire,
        public readonly int    $jour,
        public readonly int    $plageId,
        public readonly string $details,
        public readonly int    $detecteParId,
    ) {}
}

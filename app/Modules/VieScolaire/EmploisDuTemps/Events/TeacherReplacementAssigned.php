<?php

namespace App\Modules\VieScolaire\EmploisDuTemps\Events;

use Core\Event;

class TeacherReplacementAssigned extends Event
{
    public function __construct(
        public readonly int    $remplacementId,
        public readonly int    $creneauId,
        public readonly int    $enseignantAbsentId,
        public readonly ?int   $remplacantId,
        public readonly string $dateRemplacement,
        public readonly int    $creeParId,
    ) {}
}

<?php

namespace App\Modules\VieScolaire\EmploisDuTemps\Events;

use Core\Event;

class TimetablePublished extends Event
{
    public function __construct(
        public readonly int    $edtId,
        public readonly int    $classeId,
        public readonly string $anneeScolaire,
        public readonly int    $version,
        public readonly int    $publieParId,
    ) {}
}

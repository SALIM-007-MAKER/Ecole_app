<?php

namespace App\Modules\VieScolaire\Retards\Events;

use Core\Event;

class LateRejected extends Event
{
    public function __construct(
        public readonly int    $retardId,
        public readonly int    $eleveId,
        public readonly int    $classeId,
        public readonly string $anneeScolaire,
        public readonly int    $justificationId,
        public readonly string $motifRefus,
        public readonly int    $rejeteParId,
    ) {}
}

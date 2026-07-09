<?php

namespace App\Modules\VieScolaire\Recompenses\Events;

use Core\Event;

class RewardGranted extends Event
{
    public function __construct(
        public readonly int    $rewardId,
        public readonly int    $eleveId,
        public readonly int    $classeId,
        public readonly string $anneeScolaire,
        public readonly string $categorieCode,
        public readonly string $niveau,
        public readonly string $motif,
        public readonly int    $attribueParId,
    ) {}
}

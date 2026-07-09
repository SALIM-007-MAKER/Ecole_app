<?php

namespace App\Modules\VieScolaire\Recompenses\Events;

use Core\Event;

class RewardUpdated extends Event
{
    public function __construct(
        public readonly int $rewardId,
        public readonly int $eleveId,
        public readonly int $classeId,
        public readonly string $anneeScolaire,
        public readonly int $modifieParId,
    ) {}
}

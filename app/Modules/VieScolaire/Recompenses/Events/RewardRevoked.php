<?php

namespace App\Modules\VieScolaire\Recompenses\Events;

use Core\Event;

class RewardRevoked extends Event
{
    public function __construct(
        public readonly int    $rewardId,
        public readonly int    $eleveId,
        public readonly int    $classeId,
        public readonly string $anneeScolaire,
        public readonly string $motifRevocation,
        public readonly int    $revoqueParId,
    ) {}
}

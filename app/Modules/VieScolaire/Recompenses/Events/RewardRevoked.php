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
    ) {
        parent::__construct();
    }

    public function toArray(): array
    {
        return [
            'reward_id'         => $this->rewardId,
            'eleve_id'          => $this->eleveId,
            'classe_id'         => $this->classeId,
            'annee_scolaire'    => $this->anneeScolaire,
            'motif_revocation'  => $this->motifRevocation,
            'revoque_par_id'    => $this->revoqueParId,
            'fired_at'          => $this->getFiredAt(),
        ];
    }
}

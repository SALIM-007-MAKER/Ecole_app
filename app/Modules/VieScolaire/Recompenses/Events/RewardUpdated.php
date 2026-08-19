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
    ) {
        parent::__construct();
    }

    public function toArray(): array
    {
        return [
            'reward_id'       => $this->rewardId,
            'eleve_id'        => $this->eleveId,
            'classe_id'       => $this->classeId,
            'annee_scolaire'  => $this->anneeScolaire,
            'modifie_par_id'  => $this->modifieParId,
            'fired_at'        => $this->getFiredAt(),
        ];
    }
}

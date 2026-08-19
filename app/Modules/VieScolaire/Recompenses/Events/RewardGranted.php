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
            'categorie_code'  => $this->categorieCode,
            'niveau'          => $this->niveau,
            'motif'           => $this->motif,
            'attribue_par_id' => $this->attribueParId,
            'fired_at'        => $this->getFiredAt(),
        ];
    }
}

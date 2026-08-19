<?php

namespace App\Modules\VieScolaire\Retards\Events;

use Core\Event;

/**
 * Déclenché quand un élève dépasse le seuil de retards autorisé sur une période.
 */
class LateThresholdReached extends Event
{
    public function __construct(
        public readonly int    $eleveId,
        public readonly int    $classeId,
        public readonly int    $retardId,
        public readonly int    $totalRetards,
        public readonly int    $seuilAtteint,
        public readonly string $anneeScolaire,
    ) {
        parent::__construct();
    }

    public function toArray(): array
    {
        return [
            'eleve_id'       => $this->eleveId,
            'classe_id'      => $this->classeId,
            'retard_id'      => $this->retardId,
            'total_retards'  => $this->totalRetards,
            'seuil_atteint'  => $this->seuilAtteint,
            'annee_scolaire' => $this->anneeScolaire,
            'fired_at'       => $this->getFiredAt(),
        ];
    }
}

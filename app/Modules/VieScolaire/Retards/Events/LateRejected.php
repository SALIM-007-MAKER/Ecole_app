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
    ) {
        parent::__construct();
    }

    public function toArray(): array
    {
        return [
            'retard_id'        => $this->retardId,
            'eleve_id'         => $this->eleveId,
            'classe_id'        => $this->classeId,
            'annee_scolaire'   => $this->anneeScolaire,
            'justification_id' => $this->justificationId,
            'motif_refus'      => $this->motifRefus,
            'rejete_par_id'    => $this->rejeteParId,
            'fired_at'         => $this->getFiredAt(),
        ];
    }
}

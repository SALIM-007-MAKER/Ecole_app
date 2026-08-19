<?php

namespace App\Modules\VieScolaire\Retards\Events;

use Core\Event;

class LateJustified extends Event
{
    public function __construct(
        public readonly int    $retardId,
        public readonly int    $eleveId,
        public readonly int    $classeId,
        public readonly string $anneeScolaire,
        public readonly int    $justificationId,
        public readonly int    $valideParId,
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
            'valide_par_id'    => $this->valideParId,
            'fired_at'         => $this->getFiredAt(),
        ];
    }
}

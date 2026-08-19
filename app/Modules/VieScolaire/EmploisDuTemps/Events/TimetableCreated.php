<?php

namespace App\Modules\VieScolaire\EmploisDuTemps\Events;

use Core\Event;

class TimetableCreated extends Event
{
    public function __construct(
        public readonly int    $edtId,
        public readonly int    $classeId,
        public readonly string $anneeScolaire,
        public readonly string $semaineType,
        public readonly int    $creeParId,
    ) {
        parent::__construct();
    }

    public function toArray(): array
    {
        return [
            'edt_id'         => $this->edtId,
            'classe_id'      => $this->classeId,
            'annee_scolaire' => $this->anneeScolaire,
            'semaine_type'   => $this->semaineType,
            'cree_par_id'    => $this->creeParId,
            'fired_at'       => $this->getFiredAt(),
        ];
    }
}

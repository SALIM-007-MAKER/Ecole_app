<?php

namespace App\Modules\VieScolaire\EmploisDuTemps\Events;

use Core\Event;

class TimetableConflictDetected extends Event
{
    public function __construct(
        public readonly string $typeConflit,
        public readonly int    $edtId,
        public readonly int    $classeId,
        public readonly string $anneeScolaire,
        public readonly int    $jour,
        public readonly int    $plageId,
        public readonly string $details,
        public readonly int    $detecteParId,
    ) {
        parent::__construct();
    }

    public function toArray(): array
    {
        return [
            'type_conflit'   => $this->typeConflit,
            'edt_id'         => $this->edtId,
            'classe_id'      => $this->classeId,
            'annee_scolaire' => $this->anneeScolaire,
            'jour'           => $this->jour,
            'plage_id'       => $this->plageId,
            'details'        => $this->details,
            'detecte_par_id' => $this->detecteParId,
            'fired_at'       => $this->getFiredAt(),
        ];
    }
}

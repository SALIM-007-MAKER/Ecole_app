<?php

namespace App\Modules\VieScolaire\EmploisDuTemps\Events;

use Core\Event;

class TimetableUpdated extends Event
{
    public function __construct(
        public readonly int    $edtId,
        public readonly int    $classeId,
        public readonly string $anneeScolaire,
        public readonly string $action,
        public readonly int    $modifieParId,
    ) {
        parent::__construct();
    }

    public function toArray(): array
    {
        return [
            'edt_id'         => $this->edtId,
            'classe_id'      => $this->classeId,
            'annee_scolaire' => $this->anneeScolaire,
            'action'         => $this->action,
            'modifie_par_id' => $this->modifieParId,
            'fired_at'       => $this->getFiredAt(),
        ];
    }
}

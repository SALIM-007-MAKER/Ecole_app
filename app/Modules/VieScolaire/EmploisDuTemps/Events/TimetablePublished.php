<?php

namespace App\Modules\VieScolaire\EmploisDuTemps\Events;

use Core\Event;

class TimetablePublished extends Event
{
    public function __construct(
        public readonly int    $edtId,
        public readonly int    $classeId,
        public readonly string $anneeScolaire,
        public readonly int    $version,
        public readonly int    $publieParId,
    ) {
        parent::__construct();
    }

    public function toArray(): array
    {
        return [
            'edt_id'         => $this->edtId,
            'classe_id'      => $this->classeId,
            'annee_scolaire' => $this->anneeScolaire,
            'version'        => $this->version,
            'publie_par_id'  => $this->publieParId,
            'fired_at'       => $this->getFiredAt(),
        ];
    }
}

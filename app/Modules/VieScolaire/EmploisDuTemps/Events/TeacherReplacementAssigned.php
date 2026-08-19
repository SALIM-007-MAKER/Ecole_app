<?php

namespace App\Modules\VieScolaire\EmploisDuTemps\Events;

use Core\Event;

class TeacherReplacementAssigned extends Event
{
    public function __construct(
        public readonly int    $remplacementId,
        public readonly int    $creneauId,
        public readonly int    $enseignantAbsentId,
        public readonly ?int   $remplacantId,
        public readonly string $dateRemplacement,
        public readonly int    $creeParId,
    ) {
        parent::__construct();
    }

    public function toArray(): array
    {
        return [
            'remplacement_id'       => $this->remplacementId,
            'creneau_id'            => $this->creneauId,
            'enseignant_absent_id'  => $this->enseignantAbsentId,
            'remplacant_id'         => $this->remplacantId,
            'date_remplacement'     => $this->dateRemplacement,
            'cree_par_id'           => $this->creeParId,
            'fired_at'              => $this->getFiredAt(),
        ];
    }
}

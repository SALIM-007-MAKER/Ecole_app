<?php

namespace App\Modules\VieScolaire\Discipline\Events;

use Core\Event;

/**
 * Déclenché quand une sanction est prononcée sur un dossier disciplinaire.
 */
class DisciplinaryActionAssigned extends Event
{
    public function __construct(
        public readonly int    $sanctionId,
        public readonly int    $dossierId,
        public readonly int    $eleveId,
        public readonly string $typeSanction,
        public readonly string $dateSanction,
        public readonly string $anneeScolaire,
        public readonly int    $prononceParId,
    ) {
        parent::__construct();
    }

    public function toArray(): array
    {
        return [
            'sanction_id'     => $this->sanctionId,
            'dossier_id'      => $this->dossierId,
            'eleve_id'        => $this->eleveId,
            'type_sanction'   => $this->typeSanction,
            'date_sanction'   => $this->dateSanction,
            'annee_scolaire'  => $this->anneeScolaire,
            'prononce_par_id' => $this->prononceParId,
            'fired_at'        => $this->getFiredAt(),
        ];
    }
}

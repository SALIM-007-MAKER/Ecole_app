<?php

namespace App\Modules\VieScolaire\Discipline\Events;

use Core\Event;

class DisciplineAppealSubmitted extends Event
{
    public function __construct(
        public readonly int    $appelId,
        public readonly int    $sanctionId,
        public readonly int    $dossierId,
        public readonly int    $eleveId,
        public readonly string $anneeScolaire,
        public readonly int    $deposePar,
    ) {
        parent::__construct();
    }

    public function toArray(): array
    {
        return [
            'appel_id'       => $this->appelId,
            'sanction_id'    => $this->sanctionId,
            'dossier_id'     => $this->dossierId,
            'eleve_id'       => $this->eleveId,
            'annee_scolaire' => $this->anneeScolaire,
            'depose_par'     => $this->deposePar,
            'fired_at'       => $this->getFiredAt(),
        ];
    }
}

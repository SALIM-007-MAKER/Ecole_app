<?php

namespace App\Modules\Scolarite\Events;

use Core\Event;

class SchoolYearChanged extends Event
{
    public function __construct(
        public readonly int    $inscriptionId,
        public readonly int    $eleveId,
        public readonly string $ancienneAnnee,
        public readonly string $nouvelleAnnee,
        public readonly int    $changedById,
    ) {
        parent::__construct();
    }

    public function toArray(): array
    {
        return [
            'inscription_id' => $this->inscriptionId,
            'eleve_id'       => $this->eleveId,
            'ancienne_annee' => $this->ancienneAnnee,
            'nouvelle_annee' => $this->nouvelleAnnee,
            'changed_by_id'  => $this->changedById,
            'fired_at'       => $this->getFiredAt(),
        ];
    }
}

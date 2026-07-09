<?php

namespace App\Modules\Academique\Events;

use Core\Event;

class AverageCalculated extends Event
{
    public function __construct(
        public readonly int    $eleveId,
        public readonly int    $periodeId,
        public readonly ?int   $matiereId,      // null = moyenne générale de période
        public readonly float  $moyenne,
        public readonly int    $calculatedById,
    ) {
        parent::__construct();
    }

    public function getName(): string
    {
        return 'average.calculated';
    }

    public function toArray(): array
    {
        return [
            'eleve_id'         => $this->eleveId,
            'periode_id'       => $this->periodeId,
            'matiere_id'       => $this->matiereId,
            'moyenne'          => $this->moyenne,
            'calculated_by_id' => $this->calculatedById,
            'fired_at'         => $this->getFiredAt(),
        ];
    }
}

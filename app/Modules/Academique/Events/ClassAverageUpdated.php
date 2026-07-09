<?php

namespace App\Modules\Academique\Events;

use Core\Event;

class ClassAverageUpdated extends Event
{
    public function __construct(
        public readonly int    $classeId,
        public readonly int    $periodeId,
        public readonly float  $moyenne,
        public readonly float  $tauxReussite,
        public readonly int    $updatedById,
    ) {
        parent::__construct();
    }

    public function getName(): string
    {
        return 'class_average.updated';
    }

    public function toArray(): array
    {
        return [
            'classe_id'      => $this->classeId,
            'periode_id'     => $this->periodeId,
            'moyenne'        => $this->moyenne,
            'taux_reussite'  => $this->tauxReussite,
            'updated_by_id'  => $this->updatedById,
            'fired_at'       => $this->getFiredAt(),
        ];
    }
}

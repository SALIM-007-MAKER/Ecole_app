<?php

namespace App\Modules\Academique\Events;

use Core\Event;

class EvaluationLocked extends Event
{
    public function __construct(
        public readonly int    $evaluationId,
        public readonly string $libelle,
        public readonly int    $lockedById,
    ) {
        parent::__construct();
    }

    public function getName(): string
    {
        return 'evaluation.locked';
    }

    public function toArray(): array
    {
        return [
            'evaluation_id' => $this->evaluationId,
            'libelle'       => $this->libelle,
            'locked_by_id'  => $this->lockedById,
            'fired_at'      => $this->getFiredAt(),
        ];
    }
}

<?php

namespace App\Modules\Academique\Events;

use Core\Event;

class EvaluationUpdated extends Event
{
    public function __construct(
        public readonly int    $evaluationId,
        public readonly string $libelle,
        public readonly int    $updatedById,
        public readonly array  $changedFields,
    ) {
        parent::__construct();
    }

    public function getName(): string
    {
        return 'evaluation.updated';
    }

    public function toArray(): array
    {
        return [
            'evaluation_id'  => $this->evaluationId,
            'libelle'        => $this->libelle,
            'updated_by_id'  => $this->updatedById,
            'changed_fields' => $this->changedFields,
            'fired_at'       => $this->getFiredAt(),
        ];
    }
}

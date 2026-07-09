<?php

namespace App\Modules\Academique\Events;

use Core\Event;

class EvaluationArchived extends Event
{
    public function __construct(
        public readonly int    $evaluationId,
        public readonly string $libelle,
        public readonly int    $archivedById,
    ) {
        parent::__construct();
    }

    public function getName(): string
    {
        return 'evaluation.archived';
    }

    public function toArray(): array
    {
        return [
            'evaluation_id'  => $this->evaluationId,
            'libelle'        => $this->libelle,
            'archived_by_id' => $this->archivedById,
            'fired_at'       => $this->getFiredAt(),
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\RH\Evaluations\Events;

use Core\Event;

class EvaluationUpdated extends Event
{
    public function __construct(
        public readonly int    $evaluationId,
        public readonly int    $employeId,
        public readonly string $action,
        public readonly string $ancienStatut,
        public readonly string $nouveauStatut,
        public readonly int    $updatedBy
    ) {}

    public function toArray(): array
    {
        return [
            'evaluation_id' => $this->evaluationId,
            'employe_id'    => $this->employeId,
            'action'        => $this->action,
            'ancien_statut' => $this->ancienStatut,
            'nouveau_statut'=> $this->nouveauStatut,
            'updated_by'    => $this->updatedBy,
            'fired_at'      => $this->firedAt(),
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\RH\Evaluations\Events;

use Core\Event;

class DevelopmentPlanCreated extends Event
{
    public function __construct(
        public readonly int    $planId,
        public readonly int    $evaluationId,
        public readonly int    $employeId,
        public readonly string $objectif,
        public readonly int    $createdBy
    ) {}

    public function toArray(): array
    {
        return [
            'plan_id'       => $this->planId,
            'evaluation_id' => $this->evaluationId,
            'employe_id'    => $this->employeId,
            'objectif'      => mb_substr($this->objectif, 0, 100),
            'created_by'    => $this->createdBy,
            'fired_at'      => $this->firedAt(),
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\RH\Evaluations\Events;

use Core\Event;

class EvaluationCreated extends Event
{
    public function __construct(
        public readonly int    $evaluationId,
        public readonly int    $campagneId,
        public readonly int    $employeId,
        public readonly string $campagneCode,
        public readonly int    $createdBy
    ) {}

    public function toArray(): array
    {
        return [
            'evaluation_id' => $this->evaluationId,
            'campagne_id'   => $this->campagneId,
            'employe_id'    => $this->employeId,
            'campagne_code' => $this->campagneCode,
            'created_by'    => $this->createdBy,
            'fired_at'      => $this->firedAt(),
        ];
    }
}

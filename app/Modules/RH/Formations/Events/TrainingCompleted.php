<?php

declare(strict_types=1);

namespace App\Modules\RH\Formations\Events;

use Core\Event;

class TrainingCompleted extends Event
{
    public function __construct(
        public readonly int    $inscriptionId,
        public readonly int    $sessionId,
        public readonly int    $employeId,
        public readonly string $statut,
        public readonly ?float $noteEvaluation,
        public readonly int    $validatedBy
    ) {
        parent::__construct();
    }

    public function toArray(): array
    {
        return [
            'inscription_id'   => $this->inscriptionId,
            'session_id'       => $this->sessionId,
            'employe_id'       => $this->employeId,
            'statut'           => $this->statut,
            'note_evaluation'  => $this->noteEvaluation,
            'validated_by'     => $this->validatedBy,
            'fired_at'         => $this->firedAt(),
        ];
    }
}

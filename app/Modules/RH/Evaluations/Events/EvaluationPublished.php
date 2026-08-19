<?php

declare(strict_types=1);

namespace App\Modules\RH\Evaluations\Events;

use Core\Event;

class EvaluationPublished extends Event
{
    public function __construct(
        public readonly int    $evaluationId,
        public readonly int    $employeId,
        public readonly float  $scoreFinal,
        public readonly string $mention,
        public readonly int    $publiePar
    ) {
        parent::__construct();
    }

    public function toArray(): array
    {
        return [
            'evaluation_id' => $this->evaluationId,
            'employe_id'    => $this->employeId,
            'score_final'   => $this->scoreFinal,
            'mention'       => $this->mention,
            'publie_par'    => $this->publiePar,
            'fired_at'      => $this->firedAt(),
        ];
    }
}

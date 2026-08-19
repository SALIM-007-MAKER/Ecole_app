<?php

declare(strict_types=1);

namespace App\Modules\RH\Formations\Events;

use Core\Event;

class TrainingSessionOpened extends Event
{
    public function __construct(
        public readonly int    $sessionId,
        public readonly int    $formationId,
        public readonly string $codeSession,
        public readonly string $dateDebut,
        public readonly string $dateFin,
        public readonly int    $openedBy
    ) {
        parent::__construct();
    }

    public function toArray(): array
    {
        return [
            'session_id'   => $this->sessionId,
            'formation_id' => $this->formationId,
            'code_session' => $this->codeSession,
            'date_debut'   => $this->dateDebut,
            'date_fin'     => $this->dateFin,
            'opened_by'    => $this->openedBy,
            'fired_at'     => $this->firedAt(),
        ];
    }
}

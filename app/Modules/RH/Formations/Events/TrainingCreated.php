<?php

declare(strict_types=1);

namespace App\Modules\RH\Formations\Events;

use Core\Event;

class TrainingCreated extends Event
{
    public function __construct(
        public readonly int    $formationId,
        public readonly string $code,
        public readonly string $titre,
        public readonly string $type,
        public readonly int    $createdBy
    ) {
        parent::__construct();
    }

    public function toArray(): array
    {
        return [
            'formation_id' => $this->formationId,
            'code'         => $this->code,
            'titre'        => $this->titre,
            'type'         => $this->type,
            'created_by'   => $this->createdBy,
            'fired_at'     => $this->firedAt(),
        ];
    }
}

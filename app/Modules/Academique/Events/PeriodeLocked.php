<?php

namespace App\Modules\Academique\Events;

use Core\Event;

class PeriodeLocked extends Event
{
    public function __construct(
        public readonly int    $periodeId,
        public readonly string $nom,
        public readonly int    $lockedById,
    ) {
        parent::__construct();
    }

    public function getName(): string
    {
        return 'academique.periode.locked';
    }

    public function toArray(): array
    {
        return [
            'periode_id'   => $this->periodeId,
            'nom'          => $this->nom,
            'locked_by_id' => $this->lockedById,
            'fired_at'     => $this->getFiredAt(),
        ];
    }
}

<?php

namespace App\Modules\Academique\Events;

use Core\Event;

class PeriodeUnlocked extends Event
{
    public function __construct(
        public readonly int    $periodeId,
        public readonly string $nom,
        public readonly int    $unlockedById,
    ) {
        parent::__construct();
    }

    public function getName(): string
    {
        return 'academique.periode.unlocked';
    }

    public function toArray(): array
    {
        return [
            'periode_id'     => $this->periodeId,
            'nom'            => $this->nom,
            'unlocked_by_id' => $this->unlockedById,
            'fired_at'       => $this->getFiredAt(),
        ];
    }
}

<?php

namespace App\Modules\Academique\Events;

use Core\Event;

class RankingUpdated extends Event
{
    public function __construct(
        public readonly string $type,
        public readonly int    $periodeId,
        public readonly ?int   $classeId,
        public readonly int    $updatedById,
        public readonly string $motif,         // reason for update
    ) {
        parent::__construct();
    }

    public function getName(): string
    {
        return 'ranking.updated';
    }

    public function toArray(): array
    {
        return [
            'type'          => $this->type,
            'periode_id'    => $this->periodeId,
            'classe_id'     => $this->classeId,
            'updated_by_id' => $this->updatedById,
            'motif'         => $this->motif,
            'fired_at'      => $this->getFiredAt(),
        ];
    }
}

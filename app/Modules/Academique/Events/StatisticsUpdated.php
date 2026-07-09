<?php

namespace App\Modules\Academique\Events;

use Core\Event;

class StatisticsUpdated extends Event
{
    public function __construct(
        public readonly string  $scope,      // 'etablissement' | 'niveau' | 'classe' | 'matiere'
        public readonly int     $periodeId,
        public readonly ?int    $classeId,
        public readonly ?string $niveau,
        public readonly ?int    $matiereId,
        public readonly string  $triggeredBy, // 'manual' | 'auto' | 'note_published'
        public readonly int     $updatedById,
    ) {
        parent::__construct();
    }

    public function getName(): string
    {
        return 'statistics.updated';
    }

    public function toArray(): array
    {
        return [
            'scope'        => $this->scope,
            'periode_id'   => $this->periodeId,
            'classe_id'    => $this->classeId,
            'niveau'       => $this->niveau,
            'matiere_id'   => $this->matiereId,
            'triggered_by' => $this->triggeredBy,
            'updated_by_id'=> $this->updatedById,
            'fired_at'     => $this->getFiredAt(),
        ];
    }
}

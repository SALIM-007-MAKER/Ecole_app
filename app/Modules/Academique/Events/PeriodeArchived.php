<?php

namespace App\Modules\Academique\Events;

use Core\Event;

class PeriodeArchived extends Event
{
    public function __construct(
        public readonly int    $periodeId,
        public readonly string $nom,
        public readonly string $anneeScolaire,
        public readonly int    $archivedById,
    ) {
        parent::__construct();
    }

    public function getName(): string
    {
        return 'academique.periode.archived';
    }

    public function toArray(): array
    {
        return [
            'periode_id'     => $this->periodeId,
            'nom'            => $this->nom,
            'annee_scolaire' => $this->anneeScolaire,
            'archived_by_id' => $this->archivedById,
            'fired_at'       => $this->getFiredAt(),
        ];
    }
}

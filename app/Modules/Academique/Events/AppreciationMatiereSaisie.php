<?php

namespace App\Modules\Academique\Events;

use Core\Event;

class AppreciationMatiereSaisie extends Event
{
    public function __construct(
        public readonly int    $appreciationId,
        public readonly int    $eleveId,
        public readonly int    $matiereId,
        public readonly int    $periodeId,
        public readonly bool   $created,
        public readonly int    $saisieById,
    ) {
        parent::__construct();
    }

    public function getName(): string
    {
        return 'appreciation_matiere.saisie';
    }

    public function toArray(): array
    {
        return [
            'appreciation_id' => $this->appreciationId,
            'eleve_id'        => $this->eleveId,
            'matiere_id'      => $this->matiereId,
            'periode_id'      => $this->periodeId,
            'created'         => $this->created,
            'saisie_by_id'    => $this->saisieById,
            'fired_at'        => $this->getFiredAt(),
        ];
    }
}

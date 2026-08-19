<?php

namespace App\Modules\VieScolaire\Presences\Events;

use Core\Event;

class AttendanceStarted extends Event
{
    public function __construct(
        public readonly int    $appelId,
        public readonly int    $classeId,
        public readonly ?int   $matiereId,
        public readonly int    $enseignantId,
        public readonly string $dateAppel,
        public readonly string $typeAppel,    // 'journalier' | 'seance'
        public readonly string $anneeScolaire,
    ) {
        parent::__construct();
    }

    public function toArray(): array
    {
        return [
            'appel_id'       => $this->appelId,
            'classe_id'      => $this->classeId,
            'matiere_id'     => $this->matiereId,
            'enseignant_id'  => $this->enseignantId,
            'date_appel'     => $this->dateAppel,
            'type_appel'     => $this->typeAppel,
            'annee_scolaire' => $this->anneeScolaire,
            'fired_at'       => $this->getFiredAt(),
        ];
    }
}

<?php

namespace App\Events;

use Core\Event;

class AbsenceCreee extends Event
{
    public const STATUTS = ['present', 'absent', 'retard', 'excuse'];

    public function __construct(
        public readonly int    $eleveId,
        public readonly string $date,
        public readonly string $statut,
        public readonly string $session,
        public readonly int    $classeId,
        public readonly int    $saisieParId,
        public readonly string $motif     = '',
        public readonly int    $absenceId = 0,
    ) {
        parent::__construct();
    }

    public function isAbsenceOuRetard(): bool
    {
        return in_array($this->statut, ['absent', 'retard'], true);
    }

    public function toArray(): array
    {
        return [
            'eleve_id'   => $this->eleveId,
            'date'       => $this->date,
            'statut'     => $this->statut,
            'session'    => $this->session,
            'classe_id'  => $this->classeId,
            'motif'      => $this->motif,
            'absence_id' => $this->absenceId,
        ];
    }
}

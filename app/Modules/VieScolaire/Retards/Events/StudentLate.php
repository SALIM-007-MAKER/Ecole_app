<?php

namespace App\Modules\VieScolaire\Retards\Events;

use Core\Event;

/**
 * Déclenché quand un retard est créé dans vs_retards.
 * Distinct de Presences\Events\StudentLate qui concerne la session d'appel.
 */
class StudentLate extends Event
{
    public function __construct(
        public readonly int    $retardId,
        public readonly int    $eleveId,
        public readonly int    $classeId,
        public readonly string $dateRetard,
        public readonly int    $retardMinutes,
        public readonly string $heureArrivee,
        public readonly string $anneeScolaire,
        public readonly int    $saisieParId,
    ) {
        parent::__construct();
    }

    public function toArray(): array
    {
        return [
            'retard_id'      => $this->retardId,
            'eleve_id'       => $this->eleveId,
            'classe_id'      => $this->classeId,
            'date_retard'    => $this->dateRetard,
            'retard_minutes' => $this->retardMinutes,
            'heure_arrivee'  => $this->heureArrivee,
            'annee_scolaire' => $this->anneeScolaire,
            'saisie_par_id'  => $this->saisieParId,
            'fired_at'       => $this->getFiredAt(),
        ];
    }
}

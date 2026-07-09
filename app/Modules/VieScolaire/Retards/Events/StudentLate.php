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
    ) {}
}

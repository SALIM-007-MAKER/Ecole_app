<?php

namespace App\Modules\VieScolaire\Activites\Events;

use Core\Event;

class StudentRegisteredToActivity extends Event
{
    public function __construct(
        public readonly int    $inscriptionId,
        public readonly int    $activityId,
        public readonly int    $eleveId,
        public readonly string $statut,       // inscrit | liste_attente
        public readonly string $anneeScolaire,
        public readonly int    $inscritParId,
    ) {}
}

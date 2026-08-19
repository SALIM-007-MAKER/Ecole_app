<?php

namespace App\Modules\VieScolaire\Activites\Events;

use Core\Event;

class ActivityUpdated extends Event
{
    public function __construct(
        public readonly int    $activityId,
        public readonly string $anneeScolaire,
        public readonly string $action,
        public readonly int    $modifieParId,
    ) {
        parent::__construct();
    }

    public function toArray(): array
    {
        return [
            'activity_id'     => $this->activityId,
            'annee_scolaire'  => $this->anneeScolaire,
            'action'          => $this->action,
            'modifie_par_id'  => $this->modifieParId,
            'fired_at'        => $this->getFiredAt(),
        ];
    }
}

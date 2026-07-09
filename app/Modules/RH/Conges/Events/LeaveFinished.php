<?php

declare(strict_types=1);

namespace App\Modules\RH\Conges\Events;

use Core\Event;

class LeaveFinished extends Event
{
    public function __construct(
        public readonly int    $congeId,
        public readonly int    $employeId,
        public readonly string $typeCode,
        public readonly float  $dureeJours,
        public readonly string $dateRetourEffectif,
        public readonly int    $finishedBy
    ) {}

    public function toArray(): array
    {
        return [
            'conge_id'             => $this->congeId,
            'employe_id'           => $this->employeId,
            'type_code'            => $this->typeCode,
            'duree_jours'          => $this->dureeJours,
            'date_retour_effectif' => $this->dateRetourEffectif,
            'finished_by'          => $this->finishedBy,
            'fired_at'             => $this->firedAt(),
        ];
    }
}

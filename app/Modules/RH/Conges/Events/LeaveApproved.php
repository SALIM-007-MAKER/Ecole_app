<?php

declare(strict_types=1);

namespace App\Modules\RH\Conges\Events;

use Core\Event;

class LeaveApproved extends Event
{
    public function __construct(
        public readonly int    $congeId,
        public readonly int    $employeId,
        public readonly string $typeCode,
        public readonly string $dateDebut,
        public readonly string $dateFin,
        public readonly float  $dureeJours,
        public readonly int    $approvedBy
    ) {
        parent::__construct();
    }

    public function toArray(): array
    {
        return [
            'conge_id'    => $this->congeId,
            'employe_id'  => $this->employeId,
            'type_code'   => $this->typeCode,
            'date_debut'  => $this->dateDebut,
            'date_fin'    => $this->dateFin,
            'duree_jours' => $this->dureeJours,
            'approved_by' => $this->approvedBy,
            'fired_at'    => $this->firedAt(),
        ];
    }
}

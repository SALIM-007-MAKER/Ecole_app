<?php

declare(strict_types=1);

namespace App\Modules\RH\Conges\Events;

use Core\Event;

class LeaveRejected extends Event
{
    public function __construct(
        public readonly int    $congeId,
        public readonly int    $employeId,
        public readonly string $typeCode,
        public readonly string $dateDebut,
        public readonly string $dateFin,
        public readonly string $motifRejet,
        public readonly int    $rejectedBy
    ) {}

    public function toArray(): array
    {
        return [
            'conge_id'    => $this->congeId,
            'employe_id'  => $this->employeId,
            'type_code'   => $this->typeCode,
            'date_debut'  => $this->dateDebut,
            'date_fin'    => $this->dateFin,
            'motif_rejet' => $this->motifRejet,
            'rejected_by' => $this->rejectedBy,
            'fired_at'    => $this->firedAt(),
        ];
    }
}

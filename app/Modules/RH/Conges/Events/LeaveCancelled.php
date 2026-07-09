<?php

declare(strict_types=1);

namespace App\Modules\RH\Conges\Events;

use Core\Event;

class LeaveCancelled extends Event
{
    public function __construct(
        public readonly int    $congeId,
        public readonly int    $employeId,
        public readonly string $typeCode,
        public readonly string $ancienStatut,
        public readonly string $motifAnnulation,
        public readonly int    $cancelledBy
    ) {}

    public function toArray(): array
    {
        return [
            'conge_id'         => $this->congeId,
            'employe_id'       => $this->employeId,
            'type_code'        => $this->typeCode,
            'ancien_statut'    => $this->ancienStatut,
            'motif_annulation' => $this->motifAnnulation,
            'cancelled_by'     => $this->cancelledBy,
            'fired_at'         => $this->firedAt(),
        ];
    }
}

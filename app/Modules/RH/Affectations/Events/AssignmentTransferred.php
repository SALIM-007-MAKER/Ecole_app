<?php

declare(strict_types=1);

namespace App\Modules\RH\Affectations\Events;

use Core\Event;

class AssignmentTransferred extends Event
{
    public function __construct(
        public readonly int    $affectationId,
        public readonly int    $employeId,
        public readonly array  $from,   // [poste_id, departement_id, service_id, responsable_id]
        public readonly array  $to,     // [poste_id, departement_id, service_id, responsable_id]
        public readonly string $motif,
        public readonly int    $transferredBy
    ) {}

    public function toArray(): array
    {
        return [
            'affectation_id'  => $this->affectationId,
            'employe_id'      => $this->employeId,
            'from'            => $this->from,
            'to'              => $this->to,
            'motif'           => $this->motif,
            'transferred_by'  => $this->transferredBy,
            'fired_at'        => $this->firedAt(),
        ];
    }
}

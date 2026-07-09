<?php

declare(strict_types=1);

namespace App\Modules\RH\Contrats\Events;

use Core\Event;

class ContractTerminated extends Event
{
    public function __construct(
        public readonly int    $contratId,
        public readonly string $numeroContrat,
        public readonly int    $employeId,
        public readonly string $motif,
        public readonly string $dateResiliation,
        public readonly int    $terminatedBy
    ) {}

    public function toArray(): array
    {
        return [
            'contrat_id'       => $this->contratId,
            'numero_contrat'   => $this->numeroContrat,
            'employe_id'       => $this->employeId,
            'motif'            => $this->motif,
            'date_resiliation' => $this->dateResiliation,
            'terminated_by'    => $this->terminatedBy,
            'fired_at'         => $this->firedAt(),
        ];
    }
}

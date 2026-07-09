<?php

declare(strict_types=1);

namespace App\Modules\RH\Contrats\Events;

use Core\Event;

class ContractExpired extends Event
{
    public function __construct(
        public readonly int    $contratId,
        public readonly string $numeroContrat,
        public readonly int    $employeId,
        public readonly string $dateFin
    ) {}

    public function toArray(): array
    {
        return [
            'contrat_id'     => $this->contratId,
            'numero_contrat' => $this->numeroContrat,
            'employe_id'     => $this->employeId,
            'date_fin'       => $this->dateFin,
            'fired_at'       => $this->firedAt(),
        ];
    }
}

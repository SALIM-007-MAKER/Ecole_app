<?php

declare(strict_types=1);

namespace App\Modules\RH\Contrats\Events;

use Core\Event;

class ContractCreated extends Event
{
    public function __construct(
        public readonly int    $contratId,
        public readonly string $numeroContrat,
        public readonly int    $employeId,
        public readonly string $type,
        public readonly string $dateDebut,
        public readonly int    $createdBy
    ) {
        parent::__construct();
    }

    public function toArray(): array
    {
        return [
            'contrat_id'     => $this->contratId,
            'numero_contrat' => $this->numeroContrat,
            'employe_id'     => $this->employeId,
            'type'           => $this->type,
            'date_debut'     => $this->dateDebut,
            'created_by'     => $this->createdBy,
            'fired_at'       => $this->firedAt(),
        ];
    }
}

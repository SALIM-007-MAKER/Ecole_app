<?php

declare(strict_types=1);

namespace App\Modules\RH\Affectations\Events;

use Core\Event;

class AssignmentCreated extends Event
{
    public function __construct(
        public readonly int    $affectationId,
        public readonly int    $employeId,
        public readonly string $type,
        public readonly ?int   $posteId,
        public readonly ?int   $departementId,
        public readonly string $dateDebut,
        public readonly int    $createdBy
    ) {}

    public function toArray(): array
    {
        return [
            'affectation_id' => $this->affectationId,
            'employe_id'     => $this->employeId,
            'type'           => $this->type,
            'poste_id'       => $this->posteId,
            'departement_id' => $this->departementId,
            'date_debut'     => $this->dateDebut,
            'created_by'     => $this->createdBy,
            'fired_at'       => $this->firedAt(),
        ];
    }
}

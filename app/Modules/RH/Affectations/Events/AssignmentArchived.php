<?php

declare(strict_types=1);

namespace App\Modules\RH\Affectations\Events;

use Core\Event;

class AssignmentArchived extends Event
{
    public function __construct(
        public readonly int    $affectationId,
        public readonly int    $employeId,
        public readonly string $motif,
        public readonly int    $archivedBy
    ) {}

    public function toArray(): array
    {
        return [
            'affectation_id' => $this->affectationId,
            'employe_id'     => $this->employeId,
            'motif'          => $this->motif,
            'archived_by'    => $this->archivedBy,
            'fired_at'       => $this->firedAt(),
        ];
    }
}

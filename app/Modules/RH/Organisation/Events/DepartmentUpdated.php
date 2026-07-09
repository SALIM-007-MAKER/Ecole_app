<?php

declare(strict_types=1);

namespace App\Modules\RH\Organisation\Events;

use Core\Event;

class DepartmentUpdated extends Event
{
    public function __construct(
        public readonly int    $departementId,
        public readonly string $action,  // 'modification' | 'archivage' | 'restauration'
        public readonly array  $changes,
        public readonly int    $updatedBy
    ) {}

    public function toArray(): array
    {
        return [
            'departement_id' => $this->departementId,
            'action'         => $this->action,
            'changes'        => $this->changes,
            'updated_by'     => $this->updatedBy,
            'fired_at'       => $this->firedAt(),
        ];
    }
}

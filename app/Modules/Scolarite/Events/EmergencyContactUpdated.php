<?php

namespace App\Modules\Scolarite\Events;

use Core\Event;

class EmergencyContactUpdated extends Event
{
    public function __construct(
        public readonly int $familleId,
        public readonly int $updatedById,
    ) {}

    public function getName(): string { return 'famille.emergency_contact_updated'; }

    public function toArray(): array
    {
        return [
            'famille_id' => $this->familleId,
            'updated_by' => $this->updatedById,
            'fired_at'   => $this->getFiredAt(),
        ];
    }
}

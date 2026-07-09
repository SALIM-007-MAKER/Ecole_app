<?php

declare(strict_types=1);

namespace App\Modules\RH\Affectations\Events;

use Core\Event;

class AssignmentUpdated extends Event
{
    /**
     * @param string $action modification|suspension|reactivation|cloture|matiere_added|matiere_removed
     */
    public function __construct(
        public readonly int    $affectationId,
        public readonly int    $employeId,
        public readonly string $action,
        public readonly array  $changes,
        public readonly int    $updatedBy
    ) {}

    public function toArray(): array
    {
        return [
            'affectation_id' => $this->affectationId,
            'employe_id'     => $this->employeId,
            'action'         => $this->action,
            'changes'        => $this->changes,
            'updated_by'     => $this->updatedBy,
            'fired_at'       => $this->firedAt(),
        ];
    }
}

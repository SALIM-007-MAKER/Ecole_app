<?php

declare(strict_types=1);

namespace App\Modules\RH\Presences\Events;

use Core\Event;

class AttendanceUpdated extends Event
{
    /**
     * @param string $action modification|justification|regularisation|archivage|restauration
     */
    public function __construct(
        public readonly int    $presenceId,
        public readonly int    $employeId,
        public readonly string $action,
        public readonly array  $changes,
        public readonly int    $updatedBy
    ) {}

    public function toArray(): array
    {
        return [
            'presence_id' => $this->presenceId,
            'employe_id'  => $this->employeId,
            'action'      => $this->action,
            'changes'     => $this->changes,
            'updated_by'  => $this->updatedBy,
            'fired_at'    => $this->firedAt(),
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\RH\Presences\Events;

use Core\Event;

class AttendanceValidated extends Event
{
    /**
     * @param string $decision 'valide' | 'rejete'
     */
    public function __construct(
        public readonly int     $presenceId,
        public readonly int     $employeId,
        public readonly string  $datePresence,
        public readonly string  $decision,
        public readonly ?string $motifRejet,
        public readonly int     $validatedBy
    ) {
        parent::__construct();
    }

    public function toArray(): array
    {
        return [
            'presence_id'   => $this->presenceId,
            'employe_id'    => $this->employeId,
            'date_presence' => $this->datePresence,
            'decision'      => $this->decision,
            'motif_rejet'   => $this->motifRejet,
            'validated_by'  => $this->validatedBy,
            'fired_at'      => $this->firedAt(),
        ];
    }
}

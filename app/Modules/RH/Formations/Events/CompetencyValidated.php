<?php

declare(strict_types=1);

namespace App\Modules\RH\Formations\Events;

use Core\Event;

class CompetencyValidated extends Event
{
    public function __construct(
        public readonly int    $employeId,
        public readonly int    $competenceId,
        public readonly string $competenceCode,
        public readonly string $niveau,
        public readonly int    $validatedBy
    ) {
        parent::__construct();
    }

    public function toArray(): array
    {
        return [
            'employe_id'      => $this->employeId,
            'competence_id'   => $this->competenceId,
            'competence_code' => $this->competenceCode,
            'niveau'          => $this->niveau,
            'validated_by'    => $this->validatedBy,
            'fired_at'        => $this->firedAt(),
        ];
    }
}

<?php

namespace App\Modules\Academique\Events;

use Core\Event;

class EvaluationTypeDeactivated extends Event
{
    public function __construct(
        public readonly int    $typeId,
        public readonly string $code,
        public readonly string $nom,
        public readonly int    $deactivatedById,
    ) {
        parent::__construct();
    }

    public function getName(): string
    {
        return 'evaluation_type.deactivated';
    }

    public function toArray(): array
    {
        return [
            'type_id'            => $this->typeId,
            'code'               => $this->code,
            'nom'                => $this->nom,
            'deactivated_by_id'  => $this->deactivatedById,
            'fired_at'           => $this->getFiredAt(),
        ];
    }
}

<?php

namespace App\Modules\Academique\Events;

use Core\Event;

class EvaluationTypeActivated extends Event
{
    public function __construct(
        public readonly int    $typeId,
        public readonly string $code,
        public readonly string $nom,
        public readonly int    $activatedById,
    ) {
        parent::__construct();
    }

    public function getName(): string
    {
        return 'evaluation_type.activated';
    }

    public function toArray(): array
    {
        return [
            'type_id'          => $this->typeId,
            'code'             => $this->code,
            'nom'              => $this->nom,
            'activated_by_id'  => $this->activatedById,
            'fired_at'         => $this->getFiredAt(),
        ];
    }
}

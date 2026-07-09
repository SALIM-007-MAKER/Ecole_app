<?php

namespace App\Modules\Academique\Events;

use Core\Event;

class EvaluationTypeCreated extends Event
{
    public function __construct(
        public readonly int    $typeId,
        public readonly string $code,
        public readonly string $nom,
        public readonly int    $createdById,
    ) {
        parent::__construct();
    }

    public function getName(): string
    {
        return 'evaluation_type.created';
    }

    public function toArray(): array
    {
        return [
            'type_id'        => $this->typeId,
            'code'           => $this->code,
            'nom'            => $this->nom,
            'created_by_id'  => $this->createdById,
            'fired_at'       => $this->getFiredAt(),
        ];
    }
}

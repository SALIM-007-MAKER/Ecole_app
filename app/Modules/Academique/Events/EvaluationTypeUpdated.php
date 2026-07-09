<?php

namespace App\Modules\Academique\Events;

use Core\Event;

class EvaluationTypeUpdated extends Event
{
    public function __construct(
        public readonly int    $typeId,
        public readonly string $code,
        public readonly int    $updatedById,
        public readonly array  $changedFields,
    ) {
        parent::__construct();
    }

    public function getName(): string
    {
        return 'evaluation_type.updated';
    }

    public function toArray(): array
    {
        return [
            'type_id'        => $this->typeId,
            'code'           => $this->code,
            'updated_by_id'  => $this->updatedById,
            'changed_fields' => $this->changedFields,
            'fired_at'       => $this->getFiredAt(),
        ];
    }
}

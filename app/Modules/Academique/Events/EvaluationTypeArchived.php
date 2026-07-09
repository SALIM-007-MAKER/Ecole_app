<?php

namespace App\Modules\Academique\Events;

use Core\Event;

class EvaluationTypeArchived extends Event
{
    public function __construct(
        public readonly int    $typeId,
        public readonly string $code,
        public readonly string $nom,
        public readonly int    $archivedById,
    ) {
        parent::__construct();
    }

    public function getName(): string
    {
        return 'evaluation_type.archived';
    }

    public function toArray(): array
    {
        return [
            'type_id'        => $this->typeId,
            'code'           => $this->code,
            'nom'            => $this->nom,
            'archived_by_id' => $this->archivedById,
            'fired_at'       => $this->getFiredAt(),
        ];
    }
}

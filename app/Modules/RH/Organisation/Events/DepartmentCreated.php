<?php

declare(strict_types=1);

namespace App\Modules\RH\Organisation\Events;

use Core\Event;

class DepartmentCreated extends Event
{
    public function __construct(
        public readonly int    $departementId,
        public readonly string $nom,
        public readonly string $code,
        public readonly ?int   $parentId,
        public readonly int    $createdBy
    ) {}

    public function toArray(): array
    {
        return [
            'departement_id' => $this->departementId,
            'nom'            => $this->nom,
            'code'           => $this->code,
            'parent_id'      => $this->parentId,
            'created_by'     => $this->createdBy,
            'fired_at'       => $this->firedAt(),
        ];
    }
}

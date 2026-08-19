<?php

declare(strict_types=1);

namespace App\Modules\RH\Organisation\Events;

use Core\Event;

class OrganizationUpdated extends Event
{
    public function __construct(
        public readonly string $typeEntite,  // 'departement'|'service'|'poste'|'fonction'
        public readonly int    $entiteId,
        public readonly string $action,
        public readonly array  $meta,
        public readonly int    $updatedBy
    ) {
        parent::__construct();
    }

    public function toArray(): array
    {
        return [
            'type_entite' => $this->typeEntite,
            'entite_id'   => $this->entiteId,
            'action'      => $this->action,
            'meta'        => $this->meta,
            'updated_by'  => $this->updatedBy,
            'fired_at'    => $this->firedAt(),
        ];
    }
}

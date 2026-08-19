<?php

declare(strict_types=1);

namespace App\Modules\RH\Contrats\Events;

use Core\Event;

class ContractUpdated extends Event
{
    public function __construct(
        public readonly int    $contratId,
        public readonly int    $employeId,
        public readonly string $action,    // 'modification'|'suspension'|'reactivation'|'avenant'
        public readonly array  $changes,
        public readonly int    $updatedBy
    ) {
        parent::__construct();
    }

    public function toArray(): array
    {
        return [
            'contrat_id' => $this->contratId,
            'employe_id' => $this->employeId,
            'action'     => $this->action,
            'changes'    => $this->changes,
            'updated_by' => $this->updatedBy,
            'fired_at'   => $this->firedAt(),
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Communication\Events;

use Core\Event;

class DiffusionEnvoyee extends Event
{
    public function __construct(
        public readonly ?int   $groupeId,
        public readonly string $type,
        public readonly int    $totalDestinataires,
        public readonly int    $sentById,
    ) {
        parent::__construct();
    }

    public function toArray(): array
    {
        return [
            'groupe_id'           => $this->groupeId,
            'type'                => $this->type,
            'total_destinataires' => $this->totalDestinataires,
            'sent_by_id'          => $this->sentById,
        ];
    }
}

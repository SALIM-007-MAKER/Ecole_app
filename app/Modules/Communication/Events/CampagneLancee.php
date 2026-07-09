<?php

declare(strict_types=1);

namespace App\Modules\Communication\Events;

use Core\Event;

class CampagneLancee extends Event
{
    public function __construct(
        public readonly int $campagneId,
        public readonly int $totalDestinataires,
        public readonly int $launchedById,
    ) {
        parent::__construct();
    }

    public function toArray(): array
    {
        return [
            'campagne_id'         => $this->campagneId,
            'total_destinataires' => $this->totalDestinataires,
            'launched_by_id'      => $this->launchedById,
        ];
    }
}

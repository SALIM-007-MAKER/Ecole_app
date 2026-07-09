<?php

declare(strict_types=1);

namespace App\Modules\Communication\Events;

use Core\Event;

class CampagneTerminee extends Event
{
    public function __construct(
        public readonly int $campagneId,
        public readonly int $totalEnvoyes,
        public readonly int $totalEchecs,
    ) {
        parent::__construct();
    }

    public function toArray(): array
    {
        return [
            'campagne_id'   => $this->campagneId,
            'total_envoyes' => $this->totalEnvoyes,
            'total_echecs'  => $this->totalEchecs,
        ];
    }
}

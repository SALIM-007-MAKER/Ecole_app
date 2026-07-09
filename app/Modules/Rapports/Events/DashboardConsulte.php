<?php
declare(strict_types=1);

namespace App\Modules\Rapports\Events;

use Core\Event;

class DashboardConsulte extends Event
{
    public function __construct(
        public readonly string $contexte,
        public readonly int    $userId,
        public readonly int    $etablissementId,
    ) {
        parent::__construct();
    }

    public function toArray(): array
    {
        return [
            'contexte'         => $this->contexte,
            'user_id'          => $this->userId,
            'etablissement_id' => $this->etablissementId,
        ];
    }
}

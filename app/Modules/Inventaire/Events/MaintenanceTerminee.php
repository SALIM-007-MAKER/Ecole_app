<?php

declare(strict_types=1);

namespace App\Modules\Inventaire\Events;

use Core\Event;

class MaintenanceTerminee extends Event
{
    public function __construct(
        public readonly int    $maintenanceId,
        public readonly int    $articleId,
        public readonly float  $cout,
        public readonly string $rapport,
        public readonly int    $userId,
        public readonly int    $etablissementId,
    ) { parent::__construct(); }

    public function toArray(): array
    {
        return [
            'maintenance_id'   => $this->maintenanceId,
            'article_id'       => $this->articleId,
            'cout'             => $this->cout,
            'rapport'          => $this->rapport,
            'user_id'          => $this->userId,
            'etablissement_id' => $this->etablissementId,
        ];
    }
}

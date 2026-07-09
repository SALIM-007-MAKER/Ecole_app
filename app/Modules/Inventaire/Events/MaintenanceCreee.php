<?php

declare(strict_types=1);

namespace App\Modules\Inventaire\Events;

use Core\Event;

class MaintenanceCreee extends Event
{
    public function __construct(
        public readonly int    $maintenanceId,
        public readonly int    $articleId,
        public readonly string $type,
        public readonly string $datePlanifiee,
        public readonly int    $userId,
        public readonly int    $etablissementId,
    ) { parent::__construct(); }

    public function toArray(): array
    {
        return [
            'maintenance_id'   => $this->maintenanceId,
            'article_id'       => $this->articleId,
            'type'             => $this->type,
            'date_planifiee'   => $this->datePlanifiee,
            'user_id'          => $this->userId,
            'etablissement_id' => $this->etablissementId,
        ];
    }
}

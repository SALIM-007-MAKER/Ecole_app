<?php
declare(strict_types=1);

namespace App\Modules\Rapports\Events;

use Core\Event;

class RapportPlanifie extends Event
{
    public function __construct(
        public readonly int    $planificationId,
        public readonly string $nom,
        public readonly string $frequence,
        public readonly int    $userId,
        public readonly int    $etablissementId,
    ) {
        parent::__construct();
    }

    public function toArray(): array
    {
        return [
            'planification_id' => $this->planificationId,
            'nom'              => $this->nom,
            'frequence'        => $this->frequence,
            'user_id'          => $this->userId,
            'etablissement_id' => $this->etablissementId,
        ];
    }
}

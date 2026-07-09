<?php
declare(strict_types=1);

namespace App\Modules\Rapports\Events;

use Core\Event;

class RapportExecute extends Event
{
    public function __construct(
        public readonly int    $executionId,
        public readonly string $domaine,
        public readonly string $statut,
        public readonly int    $etablissementId,
        public readonly ?int   $planificationId = null,
    ) {
        parent::__construct();
    }

    public function toArray(): array
    {
        return [
            'execution_id'     => $this->executionId,
            'domaine'          => $this->domaine,
            'statut'           => $this->statut,
            'planification_id' => $this->planificationId,
            'etablissement_id' => $this->etablissementId,
        ];
    }
}

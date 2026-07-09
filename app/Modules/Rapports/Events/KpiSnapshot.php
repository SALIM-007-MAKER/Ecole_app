<?php
declare(strict_types=1);

namespace App\Modules\Rapports\Events;

use Core\Event;

class KpiSnapshot extends Event
{
    public function __construct(
        public readonly string $domaine,
        public readonly string $metrique,
        public readonly float  $valeur,
        public readonly string $periode,
        public readonly int    $etablissementId,
    ) {
        parent::__construct();
    }

    public function toArray(): array
    {
        return [
            'domaine'          => $this->domaine,
            'metrique'         => $this->metrique,
            'valeur'           => $this->valeur,
            'periode'          => $this->periode,
            'etablissement_id' => $this->etablissementId,
        ];
    }
}

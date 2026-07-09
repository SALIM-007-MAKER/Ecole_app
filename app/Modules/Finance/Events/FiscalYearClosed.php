<?php

namespace App\Modules\Finance\Events;

use Core\Event;

class FiscalYearClosed extends Event
{
    public function __construct(
        public readonly int    $exerciceId,
        public readonly string $libelle,
        public readonly string $dateFin,
        public readonly float  $resultatNet,
        public readonly int    $closedById,
    ) {
        parent::__construct();
    }

    public function getName(): string
    {
        return 'finance.comptabilite.exercice.closed';
    }

    public function toArray(): array
    {
        return [
            'exercice_id'  => $this->exerciceId,
            'libelle'      => $this->libelle,
            'date_fin'     => $this->dateFin,
            'resultat_net' => $this->resultatNet,
            'closed_by'    => $this->closedById,
        ];
    }
}

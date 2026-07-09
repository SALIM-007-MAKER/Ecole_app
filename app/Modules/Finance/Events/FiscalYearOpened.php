<?php

namespace App\Modules\Finance\Events;

use Core\Event;

class FiscalYearOpened extends Event
{
    public function __construct(
        public readonly int    $exerciceId,
        public readonly string $libelle,
        public readonly string $dateDebut,
        public readonly string $dateFin,
        public readonly int    $openedById,
    ) {
        parent::__construct();
    }

    public function getName(): string
    {
        return 'finance.comptabilite.exercice.opened';
    }

    public function toArray(): array
    {
        return [
            'exercice_id' => $this->exerciceId,
            'libelle'     => $this->libelle,
            'date_debut'  => $this->dateDebut,
            'date_fin'    => $this->dateFin,
            'opened_by'   => $this->openedById,
        ];
    }
}

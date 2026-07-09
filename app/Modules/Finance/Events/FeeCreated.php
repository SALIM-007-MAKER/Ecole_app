<?php

namespace App\Modules\Finance\Events;

use Core\Event;

class FeeCreated extends Event
{
    public function __construct(
        public readonly int     $fraisTypeId,
        public readonly string  $code,
        public readonly string  $nom,
        public readonly float   $montantDefaut,
        public readonly string  $periodicite,
        public readonly bool    $estObligatoire,
        public readonly ?string $anneeScolaire,
        public readonly int     $createdById,
    ) {
        parent::__construct();
    }

    public function getName(): string
    {
        return 'finance.fee.created';
    }

    public function toArray(): array
    {
        return [
            'frais_type_id'  => $this->fraisTypeId,
            'code'           => $this->code,
            'nom'            => $this->nom,
            'montant_defaut' => $this->montantDefaut,
            'periodicite'    => $this->periodicite,
            'est_obligatoire'=> $this->estObligatoire,
            'annee_scolaire' => $this->anneeScolaire,
            'created_by_id'  => $this->createdById,
            'fired_at'       => $this->getFiredAt(),
        ];
    }
}

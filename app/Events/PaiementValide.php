<?php

namespace App\Events;

use Core\Event;

class PaiementValide extends Event
{
    public function __construct(
        public readonly int    $paiementId,
        public readonly int    $eleveId,
        public readonly float  $montant,
        public readonly string $modePaiement,
        public readonly string $fraisNom,
        public readonly int    $fraisEleveId,
        public readonly int    $encaisseParId,
        public readonly string $reference = '',
    ) {
        parent::__construct();
    }

    public function toArray(): array
    {
        return [
            'paiement_id'    => $this->paiementId,
            'eleve_id'       => $this->eleveId,
            'montant'        => $this->montant,
            'mode_paiement'  => $this->modePaiement,
            'frais_nom'      => $this->fraisNom,
            'frais_eleve_id' => $this->fraisEleveId,
            'reference'      => $this->reference,
        ];
    }
}

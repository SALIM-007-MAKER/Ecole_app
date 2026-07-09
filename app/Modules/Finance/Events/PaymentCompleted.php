<?php

namespace App\Modules\Finance\Events;

use Core\Event;

class PaymentCompleted extends Event
{
    public function __construct(
        public readonly int    $paiementId,
        public readonly string $numero,
        public readonly int    $factureId,
        public readonly int    $eleveId,
        public readonly float  $montant,
        public readonly float  $montantApplique,
        public readonly string $modePaiement,
        public readonly string $nouveauStatutFacture,
        public readonly int    $completedById,
    ) {
        parent::__construct();
    }

    public function getName(): string
    {
        return 'finance.payment.completed';
    }

    public function toArray(): array
    {
        return [
            'paiement_id'           => $this->paiementId,
            'numero'                => $this->numero,
            'facture_id'            => $this->factureId,
            'eleve_id'              => $this->eleveId,
            'montant'               => $this->montant,
            'montant_applique'      => $this->montantApplique,
            'mode_paiement'         => $this->modePaiement,
            'nouveau_statut_facture' => $this->nouveauStatutFacture,
            'completed_by'          => $this->completedById,
        ];
    }
}

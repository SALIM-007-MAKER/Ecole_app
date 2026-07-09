<?php

namespace App\Modules\Finance\Events;

use Core\Event;

/**
 * Déclenché quand une dépense est validée (Phase décaissements V2).
 * Stub créé Phase 3.6 — intégration complète en Phase décaissements.
 */
class ExpenseValidated extends Event
{
    public function __construct(
        public readonly int    $depenseId,
        public readonly string $numero,
        public readonly string $libelle,
        public readonly float  $montant,
        public readonly string $categorie,
        public readonly string $modePaiement,
        public readonly ?int   $fournisseurId,
        public readonly int    $validatedById,
    ) {
        parent::__construct();
    }

    public function getName(): string
    {
        return 'finance.depense.validated';
    }

    public function toArray(): array
    {
        return [
            'depense_id'     => $this->depenseId,
            'numero'         => $this->numero,
            'libelle'        => $this->libelle,
            'montant'        => $this->montant,
            'categorie'      => $this->categorie,
            'mode_paiement'  => $this->modePaiement,
            'fournisseur_id' => $this->fournisseurId,
            'validated_by'   => $this->validatedById,
        ];
    }
}

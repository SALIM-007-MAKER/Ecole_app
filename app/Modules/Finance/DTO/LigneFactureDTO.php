<?php

namespace App\Modules\Finance\DTO;

class LigneFactureDTO
{
    public function __construct(
        public readonly string $libelle,
        public readonly float  $quantite,
        public readonly float  $montantUnitaire,
        public readonly ?int   $fraisTypeId      = null,
        public readonly float  $montantRemise    = 0.0,
        public readonly int    $ordre            = 1,
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            libelle:         trim($data['libelle'] ?? ''),
            quantite:        max(0.01, (float)($data['quantite'] ?? 1)),
            montantUnitaire: max(0.0, (float)($data['montant_unitaire'] ?? 0)),
            fraisTypeId:     !empty($data['frais_type_id']) ? (int)$data['frais_type_id'] : null,
            montantRemise:   max(0.0, (float)($data['montant_remise'] ?? 0)),
            ordre:           max(1, (int)($data['ordre'] ?? 1)),
        );
    }

    public function montantTotal(): float
    {
        $brut = $this->quantite * $this->montantUnitaire;
        return max(0.0, $brut - $this->montantRemise);
    }

    public function validate(): array
    {
        $errors = [];
        if (empty($this->libelle)) {
            $errors['libelle'] = 'Le libellé est requis.';
        }
        if ($this->montantUnitaire <= 0) {
            $errors['montant_unitaire'] = 'Le montant unitaire doit être positif.';
        }
        if ($this->montantRemise > ($this->quantite * $this->montantUnitaire)) {
            $errors['montant_remise'] = 'La remise ne peut pas dépasser le montant de la ligne.';
        }
        return $errors;
    }

    public function toArray(): array
    {
        return [
            'frais_type_id'    => $this->fraisTypeId,
            'libelle'          => $this->libelle,
            'quantite'         => $this->quantite,
            'montant_unitaire' => $this->montantUnitaire,
            'montant_remise'   => $this->montantRemise,
            'montant_total'    => $this->montantTotal(),
            'ordre'            => $this->ordre,
        ];
    }
}

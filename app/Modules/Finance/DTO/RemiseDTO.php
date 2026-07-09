<?php

namespace App\Modules\Finance\DTO;

class RemiseDTO
{
    public const TYPES = [
        'pourcentage'  => 'Pourcentage (%)',
        'montant_fixe' => 'Montant fixe',
        'exoneration'  => 'Exonération totale',
    ];

    public function __construct(
        public readonly string  $libelle,
        public readonly string  $typeRemise,
        public readonly float   $valeur,
        public readonly ?int    $regleExoId    = null,
        public readonly string  $justificatif  = '',
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            libelle:       trim($data['libelle']      ?? ''),
            typeRemise:    trim($data['type_remise']  ?? 'montant_fixe'),
            valeur:        max(0.0, (float)($data['valeur'] ?? 0)),
            regleExoId:    !empty($data['regle_exo_id']) ? (int)$data['regle_exo_id'] : null,
            justificatif:  trim($data['justificatif'] ?? ''),
        );
    }

    public function calculerMontant(float $montantHt): float
    {
        return match ($this->typeRemise) {
            'pourcentage' => round($montantHt * min($this->valeur, 100) / 100, 2),
            'exoneration' => $montantHt,
            default       => min($this->valeur, $montantHt),
        };
    }

    public function validate(): array
    {
        $errors = [];
        if (empty($this->libelle)) {
            $errors['libelle'] = 'Le libellé est requis.';
        }
        if (!array_key_exists($this->typeRemise, self::TYPES)) {
            $errors['type_remise'] = 'Type de remise invalide.';
        }
        if ($this->typeRemise === 'pourcentage' && ($this->valeur < 0 || $this->valeur > 100)) {
            $errors['valeur'] = 'Le pourcentage doit être entre 0 et 100.';
        }
        if ($this->typeRemise === 'montant_fixe' && $this->valeur <= 0) {
            $errors['valeur'] = 'Le montant doit être positif.';
        }
        return $errors;
    }

    public function toArray(): array
    {
        return [
            'libelle'      => $this->libelle,
            'type_remise'  => $this->typeRemise,
            'valeur'       => $this->valeur,
            'regle_exo_id' => $this->regleExoId,
            'justificatif' => $this->justificatif,
        ];
    }
}

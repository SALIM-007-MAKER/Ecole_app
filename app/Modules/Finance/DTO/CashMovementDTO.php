<?php

namespace App\Modules\Finance\DTO;

class CashMovementDTO
{
    public const TYPES_MANUELS = ['recette', 'decaissement', 'correction'];

    public const SENS_PAR_TYPE = [
        'recette'      => 'credit',
        'decaissement' => 'debit',
        'correction'   => null, // libre
        'ouverture'    => 'credit',
        'fermeture'    => 'credit',
        'annulation'   => null, // déduit automatiquement du mouvement original
    ];

    public function __construct(
        public readonly string  $type,
        public readonly string  $sens,
        public readonly float   $montant,
        public readonly string  $libelle,
        public readonly ?string $reference  = null,
        public readonly string  $source     = 'manuel',
        public readonly ?int    $paiementId = null,
    ) {}

    public static function fromRequest(array $data): self
    {
        $type = trim($data['type'] ?? '');
        // Déduire le sens si non fourni (recette → credit, décaissement → debit)
        $sensByType = self::SENS_PAR_TYPE[$type] ?? null;
        $sens = $sensByType ?? trim($data['sens'] ?? 'credit');

        return new self(
            type:       $type,
            sens:       $sens,
            montant:    (float)($data['montant'] ?? 0),
            libelle:    trim($data['libelle'] ?? ''),
            reference:  trim($data['reference'] ?? '') ?: null,
            source:     trim($data['source'] ?? 'manuel'),
            paiementId: isset($data['paiement_id']) ? (int)$data['paiement_id'] : null,
        );
    }

    public function validate(): array
    {
        $errors = [];
        if (!in_array($this->type, self::TYPES_MANUELS, true)) {
            $errors['type'] = 'Type de mouvement invalide.';
        }
        if (!in_array($this->sens, ['credit', 'debit'], true)) {
            $errors['sens'] = 'Sens invalide.';
        }
        if ($this->montant <= 0) {
            $errors['montant'] = 'Le montant doit être supérieur à zéro.';
        }
        if (empty($this->libelle)) {
            $errors['libelle'] = 'Le libellé est obligatoire.';
        }
        return $errors;
    }
}

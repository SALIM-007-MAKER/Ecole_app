<?php

namespace App\Modules\Finance\DTO;

class RefundDTO
{
    public const MODES = [
        'ESP'  => 'Espèces',
        'VIR'  => 'Virement bancaire',
        'CHQ'  => 'Chèque',
        'CB'   => 'Carte bancaire',
        'OM'   => 'Orange Money',
        'WAVE' => 'Wave',
    ];

    public function __construct(
        public readonly float  $montant,
        public readonly string $modeRemboursement,
        public readonly string $motif,
        public readonly string $referenceRemboursement = '',
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            montant:                max(0.0, (float)($data['montant'] ?? 0)),
            modeRemboursement:      strtoupper(trim($data['mode_remboursement'] ?? 'ESP')),
            motif:                  trim($data['motif'] ?? ''),
            referenceRemboursement: trim($data['reference_remboursement'] ?? ''),
        );
    }

    public function validate(): array
    {
        $errors = [];
        if ($this->montant <= 0) {
            $errors['montant'] = 'Le montant du remboursement doit être positif.';
        }
        if (!array_key_exists($this->modeRemboursement, self::MODES)) {
            $errors['mode_remboursement'] = 'Mode de remboursement invalide.';
        }
        if (empty($this->motif)) {
            $errors['motif'] = 'Le motif du remboursement est requis.';
        }
        return $errors;
    }

    public function toArray(): array
    {
        return [
            'montant'                  => $this->montant,
            'mode_remboursement'       => $this->modeRemboursement,
            'motif'                    => $this->motif,
            'reference_remboursement'  => $this->referenceRemboursement,
        ];
    }
}

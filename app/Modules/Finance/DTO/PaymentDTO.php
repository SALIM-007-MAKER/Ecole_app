<?php

namespace App\Modules\Finance\DTO;

class PaymentDTO
{
    public const MODES = [
        'ESP'   => 'Espèces',
        'CHQ'   => 'Chèque',
        'VIR'   => 'Virement bancaire',
        'CB'    => 'Carte bancaire',
        'OM'    => 'Orange Money',
        'WAVE'  => 'Wave',
        'MOOV'  => 'Moov Money',
        'AVOIR' => 'Avoir / Crédit',
    ];

    public function __construct(
        public readonly int     $factureId,
        public readonly float   $montant,
        public readonly string  $modePaiement,
        public readonly string  $datePaiement,
        public readonly string  $referenceExterne = '',
        public readonly string  $note             = '',
        public readonly ?int    $avoirId          = null,
        public readonly ?int    $echeanceId       = null,
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            factureId:        (int)($data['facture_id']        ?? 0),
            montant:          max(0.0, (float)($data['montant'] ?? 0)),
            modePaiement:     strtoupper(trim($data['mode_paiement'] ?? 'ESP')),
            datePaiement:     trim($data['date_paiement'] ?? date('Y-m-d')),
            referenceExterne: trim($data['reference_externe'] ?? ''),
            note:             trim($data['note']             ?? ''),
            avoirId:          !empty($data['avoir_id'])   ? (int)$data['avoir_id']   : null,
            echeanceId:       !empty($data['echeance_id']) ? (int)$data['echeance_id'] : null,
        );
    }

    public function validate(): array
    {
        $errors = [];

        if ($this->factureId <= 0) {
            $errors['facture_id'] = 'La facture est requise.';
        }
        if ($this->montant <= 0) {
            $errors['montant'] = 'Le montant doit être positif.';
        }
        if (!array_key_exists($this->modePaiement, self::MODES)) {
            $errors['mode_paiement'] = 'Mode de paiement invalide.';
        }
        if ($this->modePaiement === 'AVOIR' && !$this->avoirId) {
            $errors['avoir_id'] = 'Sélectionnez un avoir pour ce mode de paiement.';
        }
        if (empty($this->datePaiement)) {
            $errors['date_paiement'] = 'La date de paiement est requise.';
        } else {
            $d = \DateTime::createFromFormat('Y-m-d', $this->datePaiement);
            if (!$d || $d->format('Y-m-d') !== $this->datePaiement) {
                $errors['date_paiement'] = 'Format de date invalide.';
            }
        }
        if (in_array($this->modePaiement, ['CHQ','VIR'], true) && empty($this->referenceExterne)) {
            $errors['reference_externe'] = 'La référence est requise pour ce mode de paiement.';
        }

        return $errors;
    }

    public function toArray(): array
    {
        return [
            'facture_id'        => $this->factureId,
            'montant'           => $this->montant,
            'mode_paiement'     => $this->modePaiement,
            'date_paiement'     => $this->datePaiement,
            'reference_externe' => $this->referenceExterne,
            'note'              => $this->note,
            'avoir_id'          => $this->avoirId,
            'echeance_id'       => $this->echeanceId,
        ];
    }
}

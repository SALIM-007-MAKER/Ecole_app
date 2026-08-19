<?php

namespace App\Modules\Finance\DTO;

class DecaissementDTO
{
    public function __construct(
        public readonly string  $libelle,
        public readonly float   $montant,
        public readonly string  $dateDepense,
        public readonly ?int    $categorieId     = null,
        public readonly ?int    $fournisseurId   = null,
        public readonly string  $description     = '',
        public readonly ?string $dateEcheance    = null,
        public readonly string  $referenceExterne = '',
        public readonly string  $note            = '',
        public readonly string  $devise          = 'XOF',
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            libelle:          trim($data['libelle'] ?? ''),
            montant:          max(0.0, (float)str_replace(',', '.', $data['montant'] ?? '0')),
            dateDepense:      trim($data['date_depense'] ?? date('Y-m-d')),
            categorieId:      !empty($data['categorie_id'])   ? (int)$data['categorie_id']   : null,
            fournisseurId:    !empty($data['fournisseur_id']) ? (int)$data['fournisseur_id'] : null,
            description:      trim($data['description'] ?? ''),
            dateEcheance:     !empty($data['date_echeance']) ? trim($data['date_echeance']) : null,
            referenceExterne: trim($data['reference_externe'] ?? ''),
            note:             trim($data['note'] ?? ''),
            devise:           strtoupper(trim($data['devise'] ?? 'XOF')),
        );
    }

    public function validate(): array
    {
        $errors = [];

        if ($this->libelle === '') {
            $errors['libelle'] = 'Le libellé est requis.';
        } elseif (mb_strlen($this->libelle) > 255) {
            $errors['libelle'] = 'Le libellé ne peut pas dépasser 255 caractères.';
        }

        if ($this->montant <= 0) {
            $errors['montant'] = 'Le montant doit être positif.';
        }

        if ($this->dateDepense === '') {
            $errors['date_depense'] = 'La date de dépense est requise.';
        } else {
            $d = \DateTime::createFromFormat('Y-m-d', $this->dateDepense);
            if (!$d || $d->format('Y-m-d') !== $this->dateDepense) {
                $errors['date_depense'] = 'Date de dépense invalide.';
            }
        }

        if ($this->dateEcheance !== null) {
            $d = \DateTime::createFromFormat('Y-m-d', $this->dateEcheance);
            if (!$d || $d->format('Y-m-d') !== $this->dateEcheance) {
                $errors['date_echeance'] = 'Date d\'échéance invalide.';
            }
        }

        return $errors;
    }

    public function toArray(): array
    {
        return [
            'libelle'           => $this->libelle,
            'description'       => $this->description ?: null,
            'montant'           => $this->montant,
            'devise'            => $this->devise,
            'date_depense'      => $this->dateDepense,
            'date_echeance'     => $this->dateEcheance,
            'categorie_id'      => $this->categorieId,
            'fournisseur_id'    => $this->fournisseurId,
            'reference_externe' => $this->referenceExterne ?: null,
            'note'              => $this->note ?: null,
        ];
    }
}

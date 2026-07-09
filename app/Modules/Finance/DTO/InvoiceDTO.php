<?php

namespace App\Modules\Finance\DTO;

class InvoiceDTO
{
    public function __construct(
        public readonly int     $eleveId,
        public readonly string  $anneeScolaire,
        public readonly ?string $dateEcheance = null,
        public readonly string  $note = '',
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            eleveId:       (int)($data['eleve_id'] ?? 0),
            anneeScolaire: trim($data['annee_scolaire'] ?? ''),
            dateEcheance:  !empty($data['date_echeance']) ? trim($data['date_echeance']) : null,
            note:          trim($data['note'] ?? ''),
        );
    }

    public function validate(): array
    {
        $errors = [];

        if ($this->eleveId <= 0) {
            $errors['eleve_id'] = 'Un élève est requis.';
        }

        if (empty($this->anneeScolaire)) {
            $errors['annee_scolaire'] = 'L\'année scolaire est requise.';
        } elseif (!preg_match('/^\d{4}-\d{4}$/', $this->anneeScolaire)) {
            $errors['annee_scolaire'] = 'Format attendu : AAAA-AAAA (ex: 2026-2027).';
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
            'eleve_id'       => $this->eleveId,
            'annee_scolaire' => $this->anneeScolaire,
            'date_echeance'  => $this->dateEcheance,
            'note'           => $this->note,
        ];
    }
}

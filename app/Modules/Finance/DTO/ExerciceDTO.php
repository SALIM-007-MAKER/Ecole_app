<?php

namespace App\Modules\Finance\DTO;

class ExerciceDTO
{
    public function __construct(
        public readonly string $libelle,
        public readonly string $dateDebut,
        public readonly string $dateFin,
        public readonly float  $soldeReport = 0.0,
        public readonly ?string $note       = null,
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            libelle:     trim($data['libelle']     ?? ''),
            dateDebut:   trim($data['date_debut']  ?? ''),
            dateFin:     trim($data['date_fin']    ?? ''),
            soldeReport: max(0.0, (float)($data['solde_report'] ?? 0)),
            note:        isset($data['note']) && $data['note'] !== '' ? trim($data['note']) : null,
        );
    }

    public function validate(): array
    {
        $errors = [];

        if ($this->libelle === '') {
            $errors['libelle'] = 'Le libellé de l\'exercice est obligatoire.';
        }

        if ($this->dateDebut === '') {
            $errors['date_debut'] = 'La date de début est obligatoire.';
        } elseif (!strtotime($this->dateDebut)) {
            $errors['date_debut'] = 'La date de début n\'est pas valide.';
        }

        if ($this->dateFin === '') {
            $errors['date_fin'] = 'La date de fin est obligatoire.';
        } elseif (!strtotime($this->dateFin)) {
            $errors['date_fin'] = 'La date de fin n\'est pas valide.';
        }

        if (empty($errors) && strtotime($this->dateFin) <= strtotime($this->dateDebut)) {
            $errors['date_fin'] = 'La date de fin doit être postérieure à la date de début.';
        }

        return $errors;
    }

    public function toArray(): array
    {
        return [
            'libelle'      => $this->libelle,
            'date_debut'   => $this->dateDebut,
            'date_fin'     => $this->dateFin,
            'solde_report' => $this->soldeReport,
            'note'         => $this->note,
        ];
    }
}

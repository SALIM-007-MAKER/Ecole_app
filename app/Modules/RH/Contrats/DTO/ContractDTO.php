<?php

declare(strict_types=1);

namespace App\Modules\RH\Contrats\DTO;

class ContractDTO
{
    public const TYPES   = ['cdi', 'cdd', 'vacataire', 'stage', 'apprentissage', 'consultant', 'autre'];
    public const STATUTS = ['brouillon', 'actif', 'suspendu', 'expire', 'resilie'];

    public function __construct(
        public readonly int     $employeId,
        public readonly string  $type,
        public readonly string  $dateDebut,
        public readonly ?string $dateFin,
        public readonly ?string $dateSignature,
        public readonly ?int    $posteId,
        public readonly ?int    $departementId,
        public readonly ?float  $salaireBrut,
        public readonly string  $statut,
        public readonly ?string $motifCreation,
        public readonly ?string $notes
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            employeId:     (int)($data['employe_id'] ?? 0),
            type:          trim($data['type'] ?? ''),
            dateDebut:     trim($data['date_debut'] ?? ''),
            dateFin:       ($data['date_fin'] ?? '') !== '' ? trim($data['date_fin']) : null,
            dateSignature: ($data['date_signature'] ?? '') !== '' ? trim($data['date_signature']) : null,
            posteId:       ($data['poste_id'] ?? 0) > 0 ? (int)$data['poste_id'] : null,
            departementId: ($data['departement_id'] ?? 0) > 0 ? (int)$data['departement_id'] : null,
            salaireBrut:   ($data['salaire_brut'] ?? '') !== '' ? (float)$data['salaire_brut'] : null,
            statut:        trim($data['statut'] ?? 'brouillon'),
            motifCreation: ($data['motif_creation'] ?? '') !== '' ? trim($data['motif_creation']) : null,
            notes:         ($data['notes'] ?? '') !== '' ? trim($data['notes']) : null
        );
    }

    public function validate(): array
    {
        $errors = [];

        if ($this->employeId <= 0) {
            $errors['employe_id'] = 'L\'employé est obligatoire.';
        }
        if (!in_array($this->type, self::TYPES, true)) {
            $errors['type'] = 'Type de contrat invalide.';
        }
        if ($this->dateDebut === '' || !$this->isValidDate($this->dateDebut)) {
            $errors['date_debut'] = 'La date de début est obligatoire et doit être au format YYYY-MM-DD.';
        }
        if ($this->dateFin !== null) {
            if (!$this->isValidDate($this->dateFin)) {
                $errors['date_fin'] = 'La date de fin est invalide.';
            } elseif ($this->dateDebut !== '' && $this->dateFin <= $this->dateDebut) {
                $errors['date_fin'] = 'La date de fin doit être postérieure à la date de début.';
            }
        }
        // Un CDI ne doit pas avoir de date de fin (avertissement non bloquant — validé en service)
        if ($this->salaireBrut !== null && $this->salaireBrut < 0) {
            $errors['salaire_brut'] = 'Le salaire brut ne peut pas être négatif.';
        }

        return $errors;
    }

    public function toArray(): array
    {
        return [
            'employe_id'     => $this->employeId,
            'type'           => $this->type,
            'date_debut'     => $this->dateDebut,
            'date_fin'       => $this->dateFin,
            'date_signature' => $this->dateSignature,
            'poste_id'       => $this->posteId,
            'departement_id' => $this->departementId,
            'salaire_brut'   => $this->salaireBrut,
            'statut'         => $this->statut,
            'motif_creation' => $this->motifCreation,
            'notes'          => $this->notes,
        ];
    }

    private function isValidDate(string $date): bool
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return false;
        }
        [$y, $m, $d] = explode('-', $date);
        return checkdate((int)$m, (int)$d, (int)$y);
    }
}

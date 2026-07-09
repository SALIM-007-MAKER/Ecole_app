<?php

declare(strict_types=1);

namespace App\Modules\RH\Conges\DTO;

class LeaveDTO
{
    public function __construct(
        public readonly int     $employeId,
        public readonly int     $typeCongeId,
        public readonly string  $dateDebut,
        public readonly string  $dateFin,
        public readonly ?int    $contratId,
        public readonly ?int    $affectationId,
        public readonly ?string $motif,
        public readonly ?float  $dureeHeures,
    ) {}

    public static function fromRequest(array $post): self
    {
        return new self(
            employeId:     (int)($post['employe_id']      ?? 0),
            typeCongeId:   (int)($post['type_conge_id']   ?? 0),
            dateDebut:     trim($post['date_debut']        ?? ''),
            dateFin:       trim($post['date_fin']          ?? ''),
            contratId:     ($post['contrat_id'] ?? '') !== '' ? (int)$post['contrat_id']     : null,
            affectationId: ($post['affectation_id'] ?? '') !== '' ? (int)$post['affectation_id'] : null,
            motif:         trim($post['motif'] ?? '') ?: null,
            dureeHeures:   ($post['duree_heures'] ?? '') !== '' ? (float)$post['duree_heures'] : null,
        );
    }

    public function validate(): array
    {
        $errors = [];

        if ($this->employeId <= 0) {
            $errors['employe_id'] = "L'employé est obligatoire.";
        }
        if ($this->typeCongeId <= 0) {
            $errors['type_conge_id'] = 'Le type de congé est obligatoire.';
        }
        if ($this->dateDebut === '') {
            $errors['date_debut'] = 'La date de début est obligatoire.';
        }
        if ($this->dateFin === '') {
            $errors['date_fin'] = 'La date de fin est obligatoire.';
        }
        if ($this->dateDebut !== '' && $this->dateFin !== '' && $this->dateFin < $this->dateDebut) {
            $errors['date_fin'] = 'La date de fin doit être postérieure ou égale à la date de début.';
        }
        if ($this->dureeHeures !== null && ($this->dureeHeures <= 0 || $this->dureeHeures > 24)) {
            $errors['duree_heures'] = 'La durée en heures doit être entre 0 et 24.';
        }

        return $errors;
    }

    public function toArray(): array
    {
        return [
            'employe_id'     => $this->employeId,
            'type_conge_id'  => $this->typeCongeId,
            'contrat_id'     => $this->contratId,
            'affectation_id' => $this->affectationId,
            'date_debut'     => $this->dateDebut,
            'date_fin'       => $this->dateFin,
            'motif'          => $this->motif,
            'duree_heures'   => $this->dureeHeures,
        ];
    }
}

<?php

namespace App\Modules\VieScolaire\Presences\DTO;

class AttendanceSessionDTO
{
    public function __construct(
        public readonly int     $classeId,
        public readonly string  $dateAppel,
        public readonly string  $typeAppel      = 'journalier', // journalier|seance
        public readonly ?int    $matiereId      = null,
        public readonly ?string $heureDebut     = null,
        public readonly ?string $heureFin       = null,
        public readonly ?string $anneeScolaire  = null,
        public readonly ?string $observation    = null,
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            classeId:      (int)($data['classe_id']      ?? 0),
            dateAppel:     trim($data['date_appel']       ?? ''),
            typeAppel:     trim($data['type_appel']       ?? 'journalier'),
            matiereId:     isset($data['matiere_id']) && $data['matiere_id'] !== ''
                               ? (int)$data['matiere_id'] : null,
            heureDebut:    $data['heure_debut'] !== '' ? ($data['heure_debut'] ?? null) : null,
            heureFin:      $data['heure_fin']   !== '' ? ($data['heure_fin']   ?? null) : null,
            anneeScolaire: $data['annee_scolaire'] ?? null,
            observation:   $data['observation']   !== '' ? ($data['observation'] ?? null) : null,
        );
    }

    public function validate(): array
    {
        $errors = [];

        if ($this->classeId <= 0) {
            $errors['classe_id'][] = 'La classe est obligatoire.';
        }
        if (empty($this->dateAppel)) {
            $errors['date_appel'][] = "La date de l'appel est obligatoire.";
        } elseif (!\DateTime::createFromFormat('Y-m-d', $this->dateAppel)) {
            $errors['date_appel'][] = 'Format de date invalide (attendu YYYY-MM-DD).';
        }
        if (!in_array($this->typeAppel, ['journalier', 'seance'], true)) {
            $errors['type_appel'][] = "Type d'appel invalide.";
        }
        if ($this->typeAppel === 'seance' && $this->matiereId === null) {
            $errors['matiere_id'][] = "La matière est obligatoire pour un appel par séance.";
        }
        if ($this->heureDebut !== null && $this->heureFin !== null) {
            if (strtotime($this->heureDebut) >= strtotime($this->heureFin)) {
                $errors['heure_fin'][] = "L'heure de fin doit être postérieure à l'heure de début.";
            }
        }

        return $errors;
    }

    public function toArray(): array
    {
        return [
            'classe_id'      => $this->classeId,
            'date_appel'     => $this->dateAppel,
            'type_appel'     => $this->typeAppel,
            'matiere_id'     => $this->matiereId,
            'heure_debut'    => $this->heureDebut,
            'heure_fin'      => $this->heureFin,
            'annee_scolaire' => $this->anneeScolaire,
            'observation'    => $this->observation,
        ];
    }
}

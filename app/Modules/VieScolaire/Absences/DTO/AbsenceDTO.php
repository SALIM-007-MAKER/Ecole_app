<?php

namespace App\Modules\VieScolaire\Absences\DTO;

class AbsenceDTO
{
    public function __construct(
        public readonly int     $eleveId,
        public readonly string  $dateAbsence,
        public readonly string  $type          = 'absence',
        public readonly ?string $heureDebut    = null,
        public readonly ?string $heureFin      = null,
        public readonly ?float  $dureeHeures   = null,
        public readonly ?string $observation   = null,
        public readonly ?string $anneeScolaire = null,
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            eleveId:       (int)($data['eleve_id']       ?? 0),
            dateAbsence:   trim($data['date_absence']    ?? ''),
            type:          trim($data['type']            ?? 'absence'),
            heureDebut:    $data['heure_debut']  !== '' ? ($data['heure_debut']  ?? null) : null,
            heureFin:      $data['heure_fin']    !== '' ? ($data['heure_fin']    ?? null) : null,
            dureeHeures:   isset($data['duree_heures']) && $data['duree_heures'] !== ''
                               ? (float)$data['duree_heures'] : null,
            observation:   $data['observation'] !== '' ? ($data['observation'] ?? null) : null,
            anneeScolaire: $data['annee_scolaire'] ?? null,
        );
    }

    public function validate(): array
    {
        $errors = [];

        if ($this->eleveId <= 0) {
            $errors[] = "L'élève est obligatoire.";
        }
        if (empty($this->dateAbsence)) {
            $errors[] = "La date d'absence est obligatoire.";
        } elseif (!\DateTime::createFromFormat('Y-m-d', $this->dateAbsence)) {
            $errors[] = "Format de date invalide (attendu YYYY-MM-DD).";
        }
        if (!in_array($this->type, ['absence', 'retard', 'dispense'], true)) {
            $errors[] = "Type d'absence invalide.";
        }
        if ($this->heureDebut !== null && $this->heureFin !== null) {
            if (strtotime($this->heureDebut) >= strtotime($this->heureFin)) {
                $errors[] = "L'heure de fin doit être postérieure à l'heure de début.";
            }
        }

        return $errors;
    }

    public function toArray(): array
    {
        return [
            'eleve_id'      => $this->eleveId,
            'date_absence'  => $this->dateAbsence,
            'type'          => $this->type,
            'heure_debut'   => $this->heureDebut,
            'heure_fin'     => $this->heureFin,
            'duree_heures'  => $this->dureeHeures,
            'observation'   => $this->observation,
        ];
    }
}

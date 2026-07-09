<?php

namespace App\Modules\VieScolaire\Retards\DTO;

class LateDTO
{
    public function __construct(
        public readonly int     $eleveId,
        public readonly int     $classeId,
        public readonly string  $anneeScolaire,
        public readonly string  $dateRetard,
        public readonly ?string $heurePrevue,
        public readonly string  $heureArrivee,
        public readonly int     $dureeMinutes,
        public readonly ?string $observation,
    ) {}

    public static function fromRequest(array $data): self
    {
        $heurePrevue  = !empty($data['heure_prevue'])  ? trim($data['heure_prevue'])  : null;
        $heureArrivee = trim($data['heure_arrivee'] ?? '');
        $duree        = 0;

        if ($heurePrevue !== null && $heureArrivee !== '') {
            $prevue   = strtotime($heurePrevue);
            $arrivee  = strtotime($heureArrivee);
            $diffSec  = max(0, $arrivee - $prevue);
            $duree    = (int)floor($diffSec / 60);
        } elseif (!empty($data['duree_minutes'])) {
            $duree = (int)$data['duree_minutes'];
        }

        return new self(
            eleveId:       (int)($data['eleve_id']       ?? 0),
            classeId:      (int)($data['classe_id']      ?? 0),
            anneeScolaire: trim($data['annee_scolaire']  ?? ''),
            dateRetard:    trim($data['date_retard']     ?? ''),
            heurePrevue:   $heurePrevue,
            heureArrivee:  $heureArrivee,
            dureeMinutes:  $duree,
            observation:   !empty($data['observation']) ? trim($data['observation']) : null,
        );
    }

    public function validate(): array
    {
        $errors = [];

        if ($this->eleveId <= 0) {
            $errors['eleve_id'][] = 'L\'élève est obligatoire.';
        }
        if ($this->classeId <= 0) {
            $errors['classe_id'][] = 'La classe est obligatoire.';
        }
        if (empty($this->anneeScolaire)) {
            $errors['annee_scolaire'][] = 'L\'année scolaire est obligatoire.';
        }
        if (empty($this->dateRetard)) {
            $errors['date_retard'][] = 'La date est obligatoire.';
        }
        if (empty($this->heureArrivee)) {
            $errors['heure_arrivee'][] = 'L\'heure d\'arrivée est obligatoire.';
        }
        if ($this->heurePrevue !== null && $this->heureArrivee <= $this->heurePrevue) {
            $errors['heure_arrivee'][] = 'L\'heure d\'arrivée doit être postérieure à l\'heure prévue.';
        }

        return $errors;
    }

    public function toArray(): array
    {
        return [
            'eleve_id'       => $this->eleveId,
            'classe_id'      => $this->classeId,
            'annee_scolaire' => $this->anneeScolaire,
            'date_retard'    => $this->dateRetard,
            'heure_prevue'   => $this->heurePrevue,
            'heure_arrivee'  => $this->heureArrivee,
            'duree_minutes'  => $this->dureeMinutes,
            'observation'    => $this->observation,
        ];
    }
}

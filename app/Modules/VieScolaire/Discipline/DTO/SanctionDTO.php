<?php

namespace App\Modules\VieScolaire\Discipline\DTO;

class SanctionDTO
{
    const TYPES = [
        'avertissement'   => 'Avertissement',
        'blame'           => 'Blâme',
        'exclusion_cours' => 'Exclusion de cours',
        'exclusion_temp'  => 'Exclusion temporaire',
        'exclusion_def'   => 'Exclusion définitive',
        'travaux'         => 'Travaux d\'intérêt général',
        'conseil'         => 'Conseil de discipline',
        'mesure_educative'=> 'Mesure éducative',
    ];

    public function __construct(
        public readonly string  $typeSanction,
        public readonly string  $motif,
        public readonly string  $dateSanction,
        public readonly ?string $dateDebut,
        public readonly ?string $dateFin,
        public readonly ?int    $dureeJours,
        public readonly ?string $description,
        public readonly ?int    $incidentId,
    ) {}

    public static function fromRequest(array $data): self
    {
        $duree = !empty($data['duree_jours']) ? (int)$data['duree_jours'] : null;

        return new self(
            typeSanction: trim($data['type_sanction']  ?? ''),
            motif:        trim($data['motif']           ?? ''),
            dateSanction: trim($data['date_sanction']  ?? date('Y-m-d')),
            dateDebut:    !empty($data['date_debut'])   ? trim($data['date_debut'])  : null,
            dateFin:      !empty($data['date_fin'])     ? trim($data['date_fin'])    : null,
            dureeJours:   $duree,
            description:  !empty($data['description']) ? trim($data['description']) : null,
            incidentId:   !empty($data['incident_id']) ? (int)$data['incident_id']  : null,
        );
    }

    public function validate(): array
    {
        $errors = [];

        if (!isset(self::TYPES[$this->typeSanction])) {
            $errors['type_sanction'][] = 'Type de sanction invalide.';
        }
        if (mb_strlen(trim($this->motif)) < 10) {
            $errors['motif'][] = 'Le motif doit comporter au moins 10 caractères.';
        }
        if (empty($this->dateSanction)) {
            $errors['date_sanction'][] = 'La date de la sanction est obligatoire.';
        }
        if ($this->typeSanction === 'exclusion_temp' && $this->dureeJours === null) {
            $errors['duree_jours'][] = 'La durée est obligatoire pour une exclusion temporaire.';
        }

        return $errors;
    }
}

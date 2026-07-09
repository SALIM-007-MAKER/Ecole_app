<?php

namespace App\Modules\VieScolaire\Discipline\DTO;

class DisciplineDTO
{
    public function __construct(
        public readonly int     $eleveId,
        public readonly int     $classeId,
        public readonly string  $anneeScolaire,
        public readonly int     $categorieId,
        public readonly string  $gravite,
        public readonly string  $description,
        public readonly string  $dateIncident,
        public readonly ?string $heureIncident,
        public readonly ?string $lieu,
        public readonly ?int    $matiereId,
        public readonly ?string $pieceJointe,
    ) {}

    public static function fromRequest(array $data, ?string $uploadedFile = null): self
    {
        return new self(
            eleveId:       (int)($data['eleve_id']       ?? 0),
            classeId:      (int)($data['classe_id']      ?? 0),
            anneeScolaire: trim($data['annee_scolaire']  ?? ''),
            categorieId:   (int)($data['categorie_id']   ?? 0),
            gravite:       trim($data['gravite']         ?? 'mineur'),
            description:   trim($data['description']     ?? ''),
            dateIncident:  trim($data['date_incident']   ?? ''),
            heureIncident: !empty($data['heure_incident']) ? trim($data['heure_incident']) : null,
            lieu:          !empty($data['lieu'])           ? trim($data['lieu'])           : null,
            matiereId:     !empty($data['matiere_id'])     ? (int)$data['matiere_id']      : null,
            pieceJointe:   $uploadedFile,
        );
    }

    public function validate(): array
    {
        $errors = [];
        $gravitiValid = ['mineur', 'moyen', 'grave', 'tres_grave'];

        if ($this->eleveId <= 0) {
            $errors['eleve_id'][] = 'L\'élève est obligatoire.';
        }
        if ($this->classeId <= 0) {
            $errors['classe_id'][] = 'La classe est obligatoire.';
        }
        if (empty($this->anneeScolaire)) {
            $errors['annee_scolaire'][] = 'L\'année scolaire est obligatoire.';
        }
        if ($this->categorieId <= 0) {
            $errors['categorie_id'][] = 'La catégorie est obligatoire.';
        }
        if (!in_array($this->gravite, $gravitiValid, true)) {
            $errors['gravite'][] = 'Gravité invalide.';
        }
        if (mb_strlen(trim($this->description)) < 10) {
            $errors['description'][] = 'La description doit comporter au moins 10 caractères.';
        }
        if (empty($this->dateIncident)) {
            $errors['date_incident'][] = 'La date de l\'incident est obligatoire.';
        }

        return $errors;
    }

    public function toArray(): array
    {
        return [
            'categorie_id'  => $this->categorieId,
            'gravite'       => $this->gravite,
            'description'   => $this->description,
            'date_incident' => $this->dateIncident,
            'heure_incident'=> $this->heureIncident,
            'lieu'          => $this->lieu,
            'matiere_id'    => $this->matiereId,
            'piece_jointe'  => $this->pieceJointe,
        ];
    }
}

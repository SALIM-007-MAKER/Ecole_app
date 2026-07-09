<?php

namespace App\Modules\VieScolaire\Recompenses\DTO;

class RewardDTO
{
    public function __construct(
        public readonly int     $eleveId,
        public readonly int     $classeId,
        public readonly string  $anneeScolaire,
        public readonly int     $categorieId,
        public readonly string  $motif,
        public readonly string  $niveau,
        public readonly string  $dateAttribution,
        public readonly ?string $pieceJointe = null,
    ) {}

    public static function fromRequest(array $data, ?string $uploadedFile = null): self
    {
        return new self(
            eleveId:          (int)($data['eleve_id']         ?? 0),
            classeId:         (int)($data['classe_id']        ?? 0),
            anneeScolaire:    trim($data['annee_scolaire']     ?? ''),
            categorieId:      (int)($data['categorie_id']      ?? 0),
            motif:            trim($data['motif']              ?? ''),
            niveau:           trim($data['niveau']             ?? 'classe'),
            dateAttribution:  trim($data['date_attribution']   ?? date('Y-m-d')),
            pieceJointe:      $uploadedFile,
        );
    }

    public function validate(): array
    {
        $errors = [];

        if ($this->eleveId <= 0) {
            $errors[] = 'L\'élève est obligatoire.';
        }
        if ($this->classeId <= 0) {
            $errors[] = 'La classe est obligatoire.';
        }
        if (empty($this->anneeScolaire)) {
            $errors[] = 'L\'année scolaire est obligatoire.';
        }
        if ($this->categorieId <= 0) {
            $errors[] = 'La catégorie est obligatoire.';
        }
        if (mb_strlen($this->motif) < 10) {
            $errors[] = 'Le motif doit comporter au moins 10 caractères.';
        }
        if (!in_array($this->niveau, ['classe', 'etablissement', 'academique'], true)) {
            $errors[] = 'Le niveau est invalide.';
        }
        if (empty($this->dateAttribution)) {
            $errors[] = 'La date d\'attribution est obligatoire.';
        }

        return $errors;
    }

    public function toArray(): array
    {
        return [
            'eleve_id'         => $this->eleveId,
            'classe_id'        => $this->classeId,
            'annee_scolaire'   => $this->anneeScolaire,
            'categorie_id'     => $this->categorieId,
            'motif'            => $this->motif,
            'niveau'           => $this->niveau,
            'date_attribution' => $this->dateAttribution,
            'piece_jointe'     => $this->pieceJointe,
        ];
    }
}

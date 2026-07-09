<?php

namespace App\Modules\VieScolaire\Activites\DTO;

class ActivityDTO
{
    public function __construct(
        public readonly int     $categorieId,
        public readonly string  $titre,
        public readonly ?string $description,
        public readonly ?string $lieu,
        public readonly string  $dateActivite,
        public readonly string  $heureDebut,
        public readonly string  $heureFin,
        public readonly int     $capaciteMax,
        public readonly string  $anneeScolaire,
        public readonly ?int    $organisateurId,
        public readonly array   $classeIds,
        public readonly array   $responsableIds,
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            categorieId:     (int)($data['categorie_id']   ?? 0),
            titre:           trim($data['titre']            ?? ''),
            description:     trim($data['description']     ?? '') ?: null,
            lieu:            trim($data['lieu']             ?? '') ?: null,
            dateActivite:    trim($data['date_activite']    ?? ''),
            heureDebut:      trim($data['heure_debut']      ?? ''),
            heureFin:        trim($data['heure_fin']        ?? ''),
            capaciteMax:     max(1, (int)($data['capacite_max'] ?? 30)),
            anneeScolaire:   trim($data['annee_scolaire']   ?? ''),
            organisateurId:  !empty($data['organisateur_id']) ? (int)$data['organisateur_id'] : null,
            classeIds:       array_map('intval', array_filter((array)($data['classe_ids'] ?? []))),
            responsableIds:  array_map('intval', array_filter((array)($data['responsable_ids'] ?? []))),
        );
    }

    public function validate(): array
    {
        $errors = [];

        if ($this->categorieId <= 0) $errors[] = 'La catégorie est obligatoire.';
        if (mb_strlen($this->titre) < 3) $errors[] = 'Le titre doit contenir au moins 3 caractères.';
        if (empty($this->dateActivite) || !strtotime($this->dateActivite)) {
            $errors[] = 'La date de l\'activité est invalide.';
        }
        if (empty($this->heureDebut)) $errors[] = 'L\'heure de début est obligatoire.';
        if (empty($this->heureFin))   $errors[] = 'L\'heure de fin est obligatoire.';
        if (!empty($this->heureDebut) && !empty($this->heureFin) && $this->heureFin <= $this->heureDebut) {
            $errors[] = 'L\'heure de fin doit être postérieure à l\'heure de début.';
        }
        if ($this->capaciteMax < 1) $errors[] = 'La capacité maximale doit être supérieure à 0.';
        if (empty($this->anneeScolaire) || !preg_match('/^\d{4}-\d{4}$/', $this->anneeScolaire)) {
            $errors[] = 'L\'année scolaire doit être au format YYYY-YYYY.';
        }

        return $errors;
    }

    public function toArray(): array
    {
        return [
            'categorie_id'    => $this->categorieId,
            'titre'           => $this->titre,
            'description'     => $this->description,
            'lieu'            => $this->lieu,
            'date_activite'   => $this->dateActivite,
            'heure_debut'     => $this->heureDebut,
            'heure_fin'       => $this->heureFin,
            'capacite_max'    => $this->capaciteMax,
            'annee_scolaire'  => $this->anneeScolaire,
            'organisateur_id' => $this->organisateurId,
        ];
    }
}

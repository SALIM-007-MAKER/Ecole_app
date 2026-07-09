<?php

declare(strict_types=1);

namespace App\Modules\RH\Affectations\DTO;

readonly class MatiereAssignmentDTO
{
    public function __construct(
        public ?int    $matiereId,
        public ?int    $classeId,
        public ?string $niveau,
        public ?float  $heuresHebdo,
        public string  $dateDebut,
        public ?string $dateFin,
        public ?string $notes,
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            matiereId:   ($data['matiere_id']   ?? '') !== '' ? (int)$data['matiere_id']   : null,
            classeId:    ($data['classe_id']    ?? '') !== '' ? (int)$data['classe_id']    : null,
            niveau:      ($data['niveau']       ?? '') !== '' ? trim($data['niveau'])       : null,
            heuresHebdo: ($data['heures_hebdo'] ?? '') !== '' ? (float)$data['heures_hebdo'] : null,
            dateDebut:   trim($data['date_debut'] ?? ''),
            dateFin:     ($data['date_fin'] ?? '') !== '' ? trim($data['date_fin']) : null,
            notes:       ($data['notes'] ?? '') !== '' ? trim($data['notes']) : null,
        );
    }

    public function validate(): array
    {
        $errors = [];

        if ($this->matiereId === null && $this->niveau === null && $this->classeId === null) {
            $errors['matiere'] = 'Au moins une matière, une classe ou un niveau doit être renseigné.';
        }
        if ($this->dateDebut === '') {
            $errors['date_debut'] = 'La date de début est obligatoire.';
        }
        if ($this->heuresHebdo !== null && $this->heuresHebdo < 0) {
            $errors['heures_hebdo'] = 'Les heures hebdomadaires ne peuvent pas être négatives.';
        }
        if ($this->heuresHebdo !== null && $this->heuresHebdo > 40) {
            $errors['heures_hebdo'] = 'Les heures hebdomadaires ne peuvent pas dépasser 40h.';
        }

        return $errors;
    }

    public function toArray(): array
    {
        return [
            'matiere_id'   => $this->matiereId,
            'classe_id'    => $this->classeId,
            'niveau'       => $this->niveau,
            'heures_hebdo' => $this->heuresHebdo,
            'date_debut'   => $this->dateDebut,
            'date_fin'     => $this->dateFin,
            'notes'        => $this->notes,
        ];
    }
}

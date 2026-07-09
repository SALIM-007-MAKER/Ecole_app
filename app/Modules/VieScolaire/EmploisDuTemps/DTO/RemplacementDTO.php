<?php

namespace App\Modules\VieScolaire\EmploisDuTemps\DTO;

class RemplacementDTO
{
    public function __construct(
        public readonly int     $creneauId,
        public readonly string  $dateRemplacement,
        public readonly int     $enseignantAbsentId,
        public readonly ?int    $remplacantId           = null,
        public readonly ?int    $matiereRemplacementId  = null,
        public readonly ?int    $salleRemplacementId    = null,
        public readonly ?string $motifAbsence           = null,
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            creneauId:              (int)($data['creneau_id']            ?? 0),
            dateRemplacement:       trim($data['date_remplacement']       ?? ''),
            enseignantAbsentId:     (int)($data['enseignant_absent_id']   ?? 0),
            remplacantId:           isset($data['remplacant_id'])          && $data['remplacant_id']         !== '' ? (int)$data['remplacant_id']         : null,
            matiereRemplacementId:  isset($data['matiere_remplacement_id']) && $data['matiere_remplacement_id'] !== '' ? (int)$data['matiere_remplacement_id'] : null,
            salleRemplacementId:    isset($data['salle_remplacement_id'])  && $data['salle_remplacement_id']  !== '' ? (int)$data['salle_remplacement_id']  : null,
            motifAbsence:           isset($data['motif_absence'])          && $data['motif_absence']          !== '' ? trim($data['motif_absence'])         : null,
        );
    }

    public function validate(): array
    {
        $errors = [];
        if ($this->creneauId          <= 0) $errors[] = 'Le créneau est obligatoire.';
        if (empty($this->dateRemplacement))  $errors[] = 'La date est obligatoire.';
        if ($this->enseignantAbsentId <= 0)  $errors[] = 'L\'enseignant absent est obligatoire.';
        return $errors;
    }
}

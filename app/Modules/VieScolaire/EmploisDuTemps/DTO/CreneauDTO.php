<?php

namespace App\Modules\VieScolaire\EmploisDuTemps\DTO;

class CreneauDTO
{
    public function __construct(
        public readonly int     $emploiDuTempsId,
        public readonly int     $classeId,
        public readonly int     $matiereId,
        public readonly int     $enseignantId,
        public readonly ?int    $salleId,
        public readonly int     $jour,
        public readonly int     $plageId,
        public readonly string  $anneeScolaire,
        public readonly string  $typeCours   = 'cours',
        public readonly ?string $couleur     = null,
        public readonly ?string $note        = null,
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            emploiDuTempsId: (int)($data['emploi_du_temps_id'] ?? 0),
            classeId:         (int)($data['classe_id']         ?? 0),
            matiereId:        (int)($data['matiere_id']        ?? 0),
            enseignantId:     (int)($data['enseignant_id']     ?? 0),
            salleId:          isset($data['salle_id']) && $data['salle_id'] !== '' ? (int)$data['salle_id'] : null,
            jour:             (int)($data['jour']              ?? 0),
            plageId:          (int)($data['plage_id']          ?? 0),
            anneeScolaire:    trim($data['annee_scolaire']     ?? ''),
            typeCours:        trim($data['type_cours']         ?? 'cours'),
            couleur:          isset($data['couleur']) && $data['couleur'] !== '' ? trim($data['couleur']) : null,
            note:             isset($data['note'])   && $data['note']   !== '' ? trim($data['note'])   : null,
        );
    }

    public function validate(): array
    {
        $errors = [];
        if ($this->emploiDuTempsId <= 0) $errors[] = 'L\'emploi du temps est obligatoire.';
        if ($this->classeId        <= 0) $errors[] = 'La classe est obligatoire.';
        if ($this->matiereId       <= 0) $errors[] = 'La matière est obligatoire.';
        if ($this->enseignantId    <= 0) $errors[] = 'L\'enseignant est obligatoire.';
        if ($this->jour < 1 || $this->jour > 6) $errors[] = 'Le jour est invalide (1=Lundi, 6=Samedi).';
        if ($this->plageId         <= 0) $errors[] = 'La plage horaire est obligatoire.';
        if (empty($this->anneeScolaire)) $errors[] = 'L\'année scolaire est obligatoire.';
        if (!in_array($this->typeCours, ['cours','td','tp','sport','autre'], true)) {
            $errors[] = 'Le type de cours est invalide.';
        }
        return $errors;
    }

    public function toArray(): array
    {
        return [
            'emploi_du_temps_id' => $this->emploiDuTempsId,
            'classe_id'          => $this->classeId,
            'matiere_id'         => $this->matiereId,
            'enseignant_id'      => $this->enseignantId,
            'salle_id'           => $this->salleId,
            'jour'               => $this->jour,
            'plage_id'           => $this->plageId,
            'annee_scolaire'     => $this->anneeScolaire,
            'type_cours'         => $this->typeCours,
            'couleur'            => $this->couleur,
            'note'               => $this->note,
        ];
    }
}

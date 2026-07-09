<?php

namespace App\Modules\VieScolaire\EmploisDuTemps\DTO;

class TimetableDTO
{
    public function __construct(
        public readonly int     $classeId,
        public readonly string  $anneeScolaire,
        public readonly ?int    $periodeId    = null,
        public readonly string  $semaineType  = 'standard',
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            classeId:      (int)($data['classe_id']      ?? 0),
            anneeScolaire: trim($data['annee_scolaire']  ?? ''),
            periodeId:     isset($data['periode_id']) && $data['periode_id'] !== '' ? (int)$data['periode_id'] : null,
            semaineType:   trim($data['semaine_type']    ?? 'standard'),
        );
    }

    public function validate(): array
    {
        $errors = [];
        if ($this->classeId <= 0)        $errors[] = 'La classe est obligatoire.';
        if (empty($this->anneeScolaire)) $errors[] = 'L\'année scolaire est obligatoire.';
        return $errors;
    }
}

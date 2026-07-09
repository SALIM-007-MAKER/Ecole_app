<?php

namespace App\Modules\VieScolaire\EmploisDuTemps\DTO;

class TimetableFiltersDTO
{
    public function __construct(
        public readonly ?int    $classeId      = null,
        public readonly ?string $anneeScolaire = null,
        public readonly ?string $statut        = null,
        public readonly ?string $semaineType   = null,
        public readonly int     $page          = 1,
        public readonly int     $perPage       = 25,
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            classeId:      isset($data['classe_id'])      && $data['classe_id']      !== '' ? (int)$data['classe_id']           : null,
            anneeScolaire: isset($data['annee_scolaire']) && $data['annee_scolaire'] !== '' ? trim($data['annee_scolaire'])      : null,
            statut:        isset($data['statut'])         && $data['statut']         !== '' ? trim($data['statut'])              : null,
            semaineType:   isset($data['semaine_type'])   && $data['semaine_type']   !== '' ? trim($data['semaine_type'])        : null,
            page:          max(1,   (int)($data['page']     ?? 1)),
            perPage:       min(100, (int)($data['per_page'] ?? 25)),
        );
    }
}

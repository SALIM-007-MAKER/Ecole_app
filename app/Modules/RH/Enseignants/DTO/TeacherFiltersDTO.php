<?php

namespace App\Modules\RH\Enseignants\DTO;

class TeacherFiltersDTO
{
    public function __construct(
        public readonly string $q            = '',
        public readonly string $statut       = '',
        public readonly int    $matiereId    = 0,
        public readonly string $includeArch  = '',
        public readonly int    $page         = 1,
        public readonly int    $perPage      = 20,
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            q:           trim($data['q']          ?? ''),
            statut:      trim($data['statut']      ?? ''),
            matiereId:   (int)($data['matiere_id'] ?? 0),
            includeArch: trim($data['archive']     ?? ''),
            page:        max(1, (int)($data['page']     ?? 1)),
            perPage:     min(100, max(10, (int)($data['per_page'] ?? 20))),
        );
    }
}

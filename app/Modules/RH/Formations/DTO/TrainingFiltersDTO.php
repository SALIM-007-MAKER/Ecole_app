<?php

declare(strict_types=1);

namespace App\Modules\RH\Formations\DTO;

class TrainingFiltersDTO
{
    public function __construct(
        public readonly string $q           = '',
        public readonly string $statut      = '',
        public readonly string $type        = '',
        public readonly int    $formationId = 0,
        public readonly int    $annee       = 0,
        public readonly bool   $includeArch  = false,
        public readonly int    $page        = 1,
        public readonly int    $perPage     = 25,
    ) {}

    public static function fromRequest(array $get): self
    {
        return new self(
            q:           trim($get['q']            ?? ''),
            statut:      trim($get['statut']        ?? ''),
            type:        trim($get['type']           ?? ''),
            formationId: (int)($get['formation_id'] ?? 0),
            annee:       (int)($get['annee']         ?? 0),
            includeArch: !empty($get['arch']),
            page:        max(1, (int)($get['page']  ?? 1)),
            perPage:     25,
        );
    }
}

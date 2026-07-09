<?php

declare(strict_types=1);

namespace App\Modules\RH\Evaluations\DTO;

class EvaluationFiltersDTO
{
    public function __construct(
        public readonly string $q           = '',
        public readonly string $statut      = '',
        public readonly int    $campagneId  = 0,
        public readonly int    $employeId   = 0,
        public readonly int    $annee       = 0,
        public readonly string $mention     = '',
        public readonly bool   $includeArch = false,
        public readonly int    $page        = 1,
        public readonly int    $perPage     = 25,
    ) {}

    public static function fromRequest(array $get): self
    {
        return new self(
            q:           trim($get['q']           ?? ''),
            statut:      trim($get['statut']       ?? ''),
            campagneId:  (int)($get['campagne_id'] ?? 0),
            employeId:   (int)($get['employe_id']  ?? 0),
            annee:       (int)($get['annee']        ?? 0),
            mention:     trim($get['mention']       ?? ''),
            includeArch: !empty($get['arch']),
            page:        max(1, (int)($get['page']  ?? 1)),
            perPage:     25,
        );
    }
}

<?php

namespace App\Modules\VieScolaire\Activites\DTO;

class ActivityFiltersDTO
{
    public function __construct(
        public readonly ?int    $categorieId    = null,
        public readonly ?string $statut         = null,
        public readonly ?string $anneeScolaire  = null,
        public readonly ?string $dateFrom       = null,
        public readonly ?string $dateTo         = null,
        public readonly ?int    $classeId       = null,
        public readonly int     $page           = 1,
        public readonly int     $perPage        = 15,
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            categorieId:   !empty($data['categorie_id'])   ? (int)$data['categorie_id']  : null,
            statut:        !empty($data['statut'])         ? trim($data['statut'])        : null,
            anneeScolaire: !empty($data['annee_scolaire']) ? trim($data['annee_scolaire']): null,
            dateFrom:      !empty($data['date_from'])      ? trim($data['date_from'])     : null,
            dateTo:        !empty($data['date_to'])        ? trim($data['date_to'])       : null,
            classeId:      !empty($data['classe_id'])      ? (int)$data['classe_id']      : null,
            page:          max(1, (int)($data['page']      ?? 1)),
            perPage:       max(1, min(50, (int)($data['per_page'] ?? 15))),
        );
    }
}

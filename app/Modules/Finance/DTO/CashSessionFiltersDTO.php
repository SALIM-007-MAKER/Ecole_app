<?php

namespace App\Modules\Finance\DTO;

class CashSessionFiltersDTO
{
    public function __construct(
        public readonly string $q          = '',
        public readonly string $statut     = '',
        public readonly string $dateDebut  = '',
        public readonly string $dateFin    = '',
        public readonly int    $caissierId = 0,
        public readonly int    $page       = 1,
        public readonly int    $perPage    = 20,
        public readonly string $sortBy     = 'date_ouverture',
        public readonly string $sortDir    = 'DESC',
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            q:          trim($data['q'] ?? ''),
            statut:     trim($data['statut'] ?? ''),
            dateDebut:  trim($data['date_debut'] ?? ''),
            dateFin:    trim($data['date_fin'] ?? ''),
            caissierId: (int)($data['caissier_id'] ?? 0),
            page:       max(1, (int)($data['page'] ?? 1)),
            perPage:    min(100, max(5, (int)($data['per_page'] ?? 20))),
            sortBy:     trim($data['sort_by'] ?? 'date_ouverture'),
            sortDir:    strtoupper(trim($data['sort_dir'] ?? 'DESC')) === 'ASC' ? 'ASC' : 'DESC',
        );
    }
}

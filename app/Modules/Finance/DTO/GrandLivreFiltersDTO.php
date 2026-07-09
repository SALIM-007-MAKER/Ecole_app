<?php

namespace App\Modules\Finance\DTO;

class GrandLivreFiltersDTO
{
    public function __construct(
        public readonly int    $exerciceId = 0,
        public readonly string $compteCode = '',
        public readonly string $classe     = '',
        public readonly string $type       = '',
        public readonly string $dateDebut  = '',
        public readonly string $dateFin    = '',
        public readonly int    $page       = 1,
        public readonly int    $perPage    = 100,
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            exerciceId: (int)($data['exercice_id'] ?? 0),
            compteCode: trim($data['compte_code']  ?? ''),
            classe:     trim($data['classe']       ?? ''),
            type:       trim($data['type']         ?? ''),
            dateDebut:  trim($data['date_debut']   ?? ''),
            dateFin:    trim($data['date_fin']      ?? ''),
            page:       max(1, (int)($data['page']     ?? 1)),
            perPage:    min(500, max(20, (int)($data['per_page'] ?? 100))),
        );
    }
}

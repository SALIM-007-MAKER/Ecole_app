<?php

namespace App\Modules\Scolarite\DTO;

class FamilleFiltersDTO
{
    public readonly string $q;
    public readonly string $actif;   // '1', '0', '' (tous)
    public readonly int    $page;
    public readonly int    $perPage;

    private function __construct(
        string $q,
        string $actif,
        int    $page,
        int    $perPage,
    ) {
        $this->q       = $q;
        $this->actif   = $actif;
        $this->page    = max(1, $page);
        $this->perPage = max(5, min(100, $perPage));
    }

    public static function fromRequest(array $data): self
    {
        return new self(
            q:       trim($data['q'] ?? ''),
            actif:   isset($data['actif']) && $data['actif'] !== '' ? $data['actif'] : '1',
            page:    (int)($data['page'] ?? 1),
            perPage: (int)($data['per_page'] ?? 20),
        );
    }
}

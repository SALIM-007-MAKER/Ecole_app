<?php

declare(strict_types=1);

namespace App\Modules\RH\Organisation\DTO;

class OrganizationFiltersDTO
{
    public function __construct(
        public readonly string $q             = '',
        public readonly string $type          = '',   // 'departements'|'services'|'postes'|'fonctions'
        public readonly ?int   $departementId = null,
        public readonly string $categorie     = '',   // pour postes
        public readonly bool   $includeArch   = false,
        public readonly int    $page          = 1,
        public readonly int    $perPage       = 20
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            q:             trim($data['q'] ?? ''),
            type:          trim($data['type'] ?? ''),
            departementId: ($data['departement_id'] ?? 0) > 0 ? (int)$data['departement_id'] : null,
            categorie:     trim($data['categorie'] ?? ''),
            includeArch:   isset($data['archive']) && (bool)$data['archive'],
            page:          max(1, (int)($data['page'] ?? 1)),
            perPage:       min(100, max(10, (int)($data['per_page'] ?? 20)))
        );
    }

    public function offset(): int
    {
        return ($this->page - 1) * $this->perPage;
    }
}

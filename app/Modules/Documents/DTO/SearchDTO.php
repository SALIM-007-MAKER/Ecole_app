<?php

declare(strict_types=1);

namespace App\Modules\Documents\DTO;

class SearchDTO
{
    public function __construct(
        public readonly string $query,
        public readonly string $moduleSource    = '',
        public readonly string $statut          = 'actif',
        public readonly bool   $excludeSecret   = true,
        public readonly int    $etablissementId = 1,
        public readonly int    $page            = 1,
        public readonly int    $perPage         = 20,
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            query:           trim($data['q'] ?? $data['query'] ?? ''),
            moduleSource:    trim($data['module_source'] ?? ''),
            statut:          trim($data['statut'] ?? 'actif'),
            excludeSecret:   !isset($data['include_secret']) || empty($data['include_secret']),
            etablissementId: (int)($data['etablissement_id'] ?? 1),
            page:            max(1, (int)($data['page'] ?? 1)),
            perPage:         min(100, max(5, (int)($data['per_page'] ?? 20))),
        );
    }
}

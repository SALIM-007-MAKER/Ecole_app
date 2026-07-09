<?php

declare(strict_types=1);

namespace App\Modules\RH\Documents\DTO;

class HRDocumentFiltersDTO
{
    public function __construct(
        public readonly string $q               = '',
        public readonly string $type            = '',
        public readonly string $statut          = '',
        public readonly int    $employeId       = 0,
        public readonly string $confidentialite = '',
        public readonly string $expirationAvant = '',
        public readonly bool   $includeArchive  = false,
        public readonly int    $page            = 1,
        public readonly int    $perPage         = 20,
        public readonly bool   $excludeSecret   = false,
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            q:               trim($data['q'] ?? ''),
            type:            trim($data['type'] ?? ''),
            statut:          trim($data['statut'] ?? ''),
            employeId:       (int)($data['employe_id'] ?? 0),
            confidentialite: trim($data['confidentialite'] ?? ''),
            expirationAvant: trim($data['expiration_avant'] ?? ''),
            includeArchive:  isset($data['include_archive']) && $data['include_archive'] === '1',
            page:            max(1, (int)($data['page'] ?? 1)),
            perPage:         (int)($data['per_page'] ?? 20),
        );
    }
}

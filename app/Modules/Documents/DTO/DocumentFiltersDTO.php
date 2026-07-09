<?php

declare(strict_types=1);

namespace App\Modules\Documents\DTO;

class DocumentFiltersDTO
{
    public function __construct(
        public readonly string $q               = '',
        public readonly string $moduleSource    = '',
        public readonly string $entiteType      = '',
        public readonly int    $entiteId        = 0,
        public readonly int    $folderId        = 0,
        public readonly int    $categorieId     = 0,
        public readonly string $statut          = '',
        public readonly string $confidentialite = '',
        public readonly array  $tagIds          = [],
        public readonly string $dateEmissionMin = '',
        public readonly string $dateEmissionMax = '',
        public readonly string $dateExpirationAvant = '',
        public readonly bool   $includeArchive  = false,
        public readonly bool   $excludeSecret   = false,
        public readonly int    $etablissementId = 1,
        public readonly int    $page            = 1,
        public readonly int    $perPage         = 20,
        public readonly string $tri             = 'created_at',
        public readonly string $ordre           = 'DESC',
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            q:               trim($data['q'] ?? ''),
            moduleSource:    trim($data['module_source'] ?? ''),
            entiteType:      trim($data['entite_type'] ?? ''),
            entiteId:        (int)($data['entite_id'] ?? 0),
            folderId:        (int)($data['folder_id'] ?? 0),
            categorieId:     (int)($data['categorie_id'] ?? 0),
            statut:          trim($data['statut'] ?? ''),
            confidentialite: trim($data['confidentialite'] ?? ''),
            tagIds:          isset($data['tags']) ? array_map('intval', (array)$data['tags']) : [],
            dateEmissionMin: trim($data['date_emission_min'] ?? ''),
            dateEmissionMax: trim($data['date_emission_max'] ?? ''),
            dateExpirationAvant: trim($data['date_expiration_avant'] ?? ''),
            includeArchive:  !empty($data['include_archive']),
            excludeSecret:   !empty($data['exclude_secret']),
            etablissementId: (int)($data['etablissement_id'] ?? 1),
            page:            max(1, (int)($data['page'] ?? 1)),
            perPage:         min(100, max(5, (int)($data['per_page'] ?? 20))),
            tri:             in_array($data['tri'] ?? '', ['created_at','titre','date_expiration','taille_octets'], true)
                                ? $data['tri'] : 'created_at',
            ordre:           strtoupper($data['ordre'] ?? '') === 'ASC' ? 'ASC' : 'DESC',
        );
    }
}

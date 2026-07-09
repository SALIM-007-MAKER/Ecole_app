<?php

declare(strict_types=1);

namespace App\Modules\Inventaire\DTO;

class ArticleFiltersDTO
{
    public function __construct(
        public readonly ?string $search,
        public readonly ?string $type,
        public readonly ?int    $categorieId,
        public readonly ?int    $fournisseurId,
        public readonly ?bool   $actif,
        public readonly ?string $alerte,
        public readonly int     $page,
        public readonly int     $perPage,
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            search:        $data['search'] ?? null,
            type:          $data['type'] ?? null,
            categorieId:   isset($data['categorie_id']) ? (int)$data['categorie_id'] : null,
            fournisseurId: isset($data['fournisseur_id']) ? (int)$data['fournisseur_id'] : null,
            actif:         isset($data['actif']) ? (bool)$data['actif'] : null,
            alerte:        $data['alerte'] ?? null,
            page:          max(1, (int)($data['page'] ?? 1)),
            perPage:       min(100, max(10, (int)($data['per_page'] ?? 25))),
        );
    }
}

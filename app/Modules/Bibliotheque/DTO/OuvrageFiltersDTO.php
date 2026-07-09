<?php

declare(strict_types=1);

namespace App\Modules\Bibliotheque\DTO;

class OuvrageFiltersDTO
{
    public function __construct(
        public readonly ?string $terme,
        public readonly ?int    $categorieId,
        public readonly ?int    $auteurId,
        public readonly ?int    $editeurId,
        public readonly ?string $langue,
        public readonly ?string $type,
        public readonly ?int    $anneeMin,
        public readonly ?int    $anneeMax,
        public readonly array   $tagIds,
        public readonly bool    $disponibleSeulement,
        public readonly string  $tri,
        public readonly int     $page,
        public readonly int     $perPage,
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            terme:               $data['q'] ?? $data['terme'] ?? null,
            categorieId:         isset($data['categorie_id']) && $data['categorie_id'] !== '' ? (int)$data['categorie_id'] : null,
            auteurId:            isset($data['auteur_id']) && $data['auteur_id'] !== '' ? (int)$data['auteur_id'] : null,
            editeurId:           isset($data['editeur_id']) && $data['editeur_id'] !== '' ? (int)$data['editeur_id'] : null,
            langue:              $data['langue'] ?? null,
            type:                $data['type'] ?? null,
            anneeMin:            isset($data['annee_min']) && $data['annee_min'] !== '' ? (int)$data['annee_min'] : null,
            anneeMax:            isset($data['annee_max']) && $data['annee_max'] !== '' ? (int)$data['annee_max'] : null,
            tagIds:              array_map('intval', (array)($data['tag_ids'] ?? [])),
            disponibleSeulement: isset($data['disponible']) && $data['disponible'] === '1',
            tri:                 $data['tri'] ?? 'titre',
            page:                max(1, (int)($data['page'] ?? 1)),
            perPage:             min(100, max(10, (int)($data['per_page'] ?? 20))),
        );
    }
}

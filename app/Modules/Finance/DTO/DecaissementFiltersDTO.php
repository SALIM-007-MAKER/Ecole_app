<?php

namespace App\Modules\Finance\DTO;

class DecaissementFiltersDTO
{
    public function __construct(
        public readonly ?string $q             = null,
        public readonly ?string $statut        = null,
        public readonly ?int    $categorieId   = null,
        public readonly ?int    $fournisseurId = null,
        public readonly ?string $dateDebut     = null,
        public readonly ?string $dateFin       = null,
        public readonly ?string $origine       = null,
        public readonly int     $page          = 1,
        public readonly int     $perPage       = 20,
        public readonly string  $sortBy        = 'date_depense',
        public readonly string  $sortDir       = 'DESC',
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            q:             !empty($data['q'])             ? trim($data['q'])             : null,
            statut:        !empty($data['statut'])        ? trim($data['statut'])        : null,
            categorieId:   !empty($data['categorie_id'])  ? (int)$data['categorie_id']   : null,
            fournisseurId: !empty($data['fournisseur_id']) ? (int)$data['fournisseur_id'] : null,
            dateDebut:     !empty($data['date_debut'])    ? trim($data['date_debut'])    : null,
            dateFin:       !empty($data['date_fin'])      ? trim($data['date_fin'])      : null,
            origine:       in_array($data['origine'] ?? '', ['operationnelle', 'migration_v1'], true)
                               ? $data['origine'] : null,
            page:          max(1, (int)($data['page']     ?? 1)),
            perPage:       min(100, max(10, (int)($data['per_page'] ?? 20))),
            sortBy:        in_array($data['sort_by'] ?? '', ['numero','date_depense','montant','statut'], true)
                               ? $data['sort_by'] : 'date_depense',
            sortDir:       strtoupper($data['sort_dir'] ?? '') === 'ASC' ? 'ASC' : 'DESC',
        );
    }
}

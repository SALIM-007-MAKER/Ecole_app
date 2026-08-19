<?php

namespace App\Modules\Finance\DTO;

class InvoiceFiltersDTO
{
    public function __construct(
        public readonly ?string $q              = null,
        public readonly ?string $statut         = null,
        public readonly ?string $anneeScolaire  = null,
        public readonly ?int    $eleveId        = null,
        public readonly ?int    $classeId       = null,
        public readonly ?string $niveau         = null,
        public readonly ?string $dateDebut      = null,
        public readonly ?string $dateFin        = null,
        public readonly ?string $origine        = null,
        public readonly int     $page           = 1,
        public readonly int     $perPage        = 20,
        public readonly string  $sortBy         = 'date_emission',
        public readonly string  $sortDir        = 'DESC',
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            q:             !empty($data['q'])              ? trim($data['q'])             : null,
            statut:        !empty($data['statut'])         ? trim($data['statut'])        : null,
            anneeScolaire: !empty($data['annee_scolaire']) ? trim($data['annee_scolaire']): null,
            eleveId:       !empty($data['eleve_id'])       ? (int)$data['eleve_id']       : null,
            classeId:      !empty($data['classe_id'])      ? (int)$data['classe_id']      : null,
            niveau:        !empty($data['niveau'])         ? trim($data['niveau'])        : null,
            dateDebut:     !empty($data['date_debut'])     ? trim($data['date_debut'])    : null,
            dateFin:       !empty($data['date_fin'])       ? trim($data['date_fin'])      : null,
            origine:       in_array($data['origine'] ?? '', ['operationnelle', 'migration_v1'], true)
                               ? $data['origine'] : null,
            page:          max(1, (int)($data['page']     ?? 1)),
            perPage:       min(100, max(10, (int)($data['per_page'] ?? 20))),
            sortBy:        in_array($data['sort_by'] ?? '', ['numero','date_emission','date_echeance','montant_total','statut'])
                               ? $data['sort_by'] : 'date_emission',
            sortDir:       strtoupper($data['sort_dir'] ?? '') === 'ASC' ? 'ASC' : 'DESC',
        );
    }
}

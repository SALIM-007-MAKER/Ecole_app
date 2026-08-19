<?php

namespace App\Modules\Finance\DTO;

class PaymentFiltersDTO
{
    public function __construct(
        public readonly ?string $q              = null,
        public readonly ?string $statut         = null,
        public readonly ?string $modePaiement   = null,
        public readonly ?int    $factureId      = null,
        public readonly ?int    $eleveId        = null,
        public readonly ?string $dateDebut      = null,
        public readonly ?string $dateFin        = null,
        public readonly ?string $anneeScolaire  = null,
        public readonly ?string $origine        = null,
        public readonly int     $page           = 1,
        public readonly int     $perPage        = 25,
        public readonly string  $sortBy         = 'date_paiement',
        public readonly string  $sortDir        = 'DESC',
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            q:             !empty($data['q'])              ? trim($data['q'])              : null,
            statut:        !empty($data['statut'])         ? trim($data['statut'])         : null,
            modePaiement:  !empty($data['mode_paiement'])  ? trim($data['mode_paiement'])  : null,
            factureId:     !empty($data['facture_id'])     ? (int)$data['facture_id']      : null,
            eleveId:       !empty($data['eleve_id'])       ? (int)$data['eleve_id']        : null,
            dateDebut:     !empty($data['date_debut'])     ? trim($data['date_debut'])     : null,
            dateFin:       !empty($data['date_fin'])       ? trim($data['date_fin'])       : null,
            anneeScolaire: !empty($data['annee_scolaire']) ? trim($data['annee_scolaire']) : null,
            origine:       in_array($data['origine'] ?? '', ['operationnelle', 'migration_v1'], true)
                               ? $data['origine'] : null,
            page:          max(1, (int)($data['page']     ?? 1)),
            perPage:       min(100, max(10, (int)($data['per_page'] ?? 25))),
            sortBy:        in_array($data['sort_by'] ?? '', ['numero','date_paiement','montant','statut'])
                               ? $data['sort_by'] : 'date_paiement',
            sortDir:       strtoupper($data['sort_dir'] ?? '') === 'ASC' ? 'ASC' : 'DESC',
        );
    }
}

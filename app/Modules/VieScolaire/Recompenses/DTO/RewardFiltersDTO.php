<?php

namespace App\Modules\VieScolaire\Recompenses\DTO;

class RewardFiltersDTO
{
    public function __construct(
        public readonly ?int    $eleveId        = null,
        public readonly ?int    $classeId       = null,
        public readonly ?string $anneeScolaire  = null,
        public readonly ?string $categorieId    = null,
        public readonly ?string $niveau         = null,
        public readonly ?string $statut         = null,
        public readonly ?string $dateDebut      = null,
        public readonly ?string $dateFin        = null,
        public readonly int     $page           = 1,
        public readonly int     $perPage        = 25,
    ) {}

    public static function fromRequest(array $data): self
    {
        $perPage = min((int)($data['per_page'] ?? 25), 100);
        $page    = max((int)($data['page']     ?? 1),  1);

        return new self(
            eleveId:       isset($data['eleve_id'])    && $data['eleve_id']    !== '' ? (int)$data['eleve_id']  : null,
            classeId:      isset($data['classe_id'])   && $data['classe_id']   !== '' ? (int)$data['classe_id'] : null,
            anneeScolaire: isset($data['annee_scolaire']) && $data['annee_scolaire'] !== '' ? trim($data['annee_scolaire']) : null,
            categorieId:   isset($data['categorie_id']) && $data['categorie_id'] !== '' ? $data['categorie_id'] : null,
            niveau:        isset($data['niveau'])  && $data['niveau']  !== '' ? trim($data['niveau'])  : null,
            statut:        isset($data['statut'])  && $data['statut']  !== '' ? trim($data['statut'])  : null,
            dateDebut:     isset($data['date_debut']) && $data['date_debut'] !== '' ? trim($data['date_debut']) : null,
            dateFin:       isset($data['date_fin'])   && $data['date_fin']   !== '' ? trim($data['date_fin'])   : null,
            page:          $page,
            perPage:       $perPage,
        );
    }
}

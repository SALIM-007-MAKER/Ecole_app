<?php

namespace App\Modules\VieScolaire\Absences\DTO;

class AbsenceFiltersDTO
{
    public function __construct(
        public readonly ?int    $eleveId      = null,
        public readonly ?int    $classeId     = null,
        public readonly ?string $anneeScolaire = null,
        public readonly ?string $dateDebut    = null,
        public readonly ?string $dateFin      = null,
        public readonly ?string $type         = null,  // absence|retard|dispense
        public readonly ?string $statut       = null,  // non_justifiee|en_attente|justifiee|refusee
        public readonly int     $page         = 1,
        public readonly int     $perPage      = 25,
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            eleveId:       isset($data['eleve_id'])   && $data['eleve_id']   !== '' ? (int)$data['eleve_id']   : null,
            classeId:      isset($data['classe_id'])  && $data['classe_id']  !== '' ? (int)$data['classe_id']  : null,
            anneeScolaire: $data['annee_scolaire'] ?? null,
            dateDebut:     $data['date_debut']     ?? null,
            dateFin:       $data['date_fin']       ?? null,
            type:          $data['type']           ?? null,
            statut:        $data['statut']         ?? null,
            page:          isset($data['page'])    && $data['page'] > 0 ? (int)$data['page'] : 1,
            perPage:       isset($data['per_page']) && $data['per_page'] > 0
                               ? min((int)$data['per_page'], 100) : 25,
        );
    }
}

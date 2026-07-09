<?php

namespace App\Modules\VieScolaire\Presences\DTO;

class AttendanceFiltersDTO
{
    public function __construct(
        public readonly ?int    $classeId      = null,
        public readonly ?int    $enseignantId  = null,
        public readonly ?string $anneeScolaire = null,
        public readonly ?string $dateDebut     = null,
        public readonly ?string $dateFin       = null,
        public readonly ?string $statut        = null,  // brouillon|valide
        public readonly ?string $typeAppel     = null,  // journalier|seance
        public readonly int     $page          = 1,
        public readonly int     $perPage       = 25,
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            classeId:      isset($data['classe_id'])     && $data['classe_id']     !== '' ? (int)$data['classe_id']     : null,
            enseignantId:  isset($data['enseignant_id']) && $data['enseignant_id'] !== '' ? (int)$data['enseignant_id'] : null,
            anneeScolaire: $data['annee_scolaire'] ?? null,
            dateDebut:     $data['date_debut']     ?? null,
            dateFin:       $data['date_fin']       ?? null,
            statut:        $data['statut']         ?? null,
            typeAppel:     $data['type_appel']     ?? null,
            page:          isset($data['page'])    && $data['page'] > 0 ? (int)$data['page']    : 1,
            perPage:       isset($data['per_page'])                     ? min((int)$data['per_page'], 100) : 25,
        );
    }
}

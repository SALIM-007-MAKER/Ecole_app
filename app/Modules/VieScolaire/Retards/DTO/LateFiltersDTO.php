<?php

namespace App\Modules\VieScolaire\Retards\DTO;

class LateFiltersDTO
{
    public function __construct(
        public readonly ?int    $eleveId,
        public readonly ?int    $classeId,
        public readonly ?string $anneeScolaire,
        public readonly ?string $dateDebut,
        public readonly ?string $dateFin,
        public readonly ?string $statut,
        public readonly int     $page,
        public readonly int     $perPage,
        /** @var int[]|null Restriction serveur (jamais depuis la requête) — cf. EleveScopeTrait */
        public readonly ?array  $eleveIds = null,
    ) {}

    /** Retourne une copie restreinte au périmètre autorisé (parent/eleve). */
    public function withEleveIds(array $ids): self
    {
        return new self(
            $this->eleveId, $this->classeId, $this->anneeScolaire,
            $this->dateDebut, $this->dateFin, $this->statut,
            $this->page, $this->perPage, $ids
        );
    }

    public static function fromRequest(array $data): self
    {
        $perPage = min((int)($data['per_page'] ?? 20), 100);

        return new self(
            eleveId:       !empty($data['eleve_id'])       ? (int)$data['eleve_id']       : null,
            classeId:      !empty($data['classe_id'])      ? (int)$data['classe_id']      : null,
            anneeScolaire: !empty($data['annee_scolaire']) ? trim($data['annee_scolaire']) : null,
            dateDebut:     !empty($data['date_debut'])     ? trim($data['date_debut'])     : null,
            dateFin:       !empty($data['date_fin'])       ? trim($data['date_fin'])       : null,
            statut:        !empty($data['statut'])         ? trim($data['statut'])         : null,
            page:          max(1, (int)($data['page'] ?? 1)),
            perPage:       $perPage > 0 ? $perPage : 20,
        );
    }
}

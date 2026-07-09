<?php

namespace App\Modules\Scolarite\DTO;

class InscriptionFiltersDTO
{
    public function __construct(
        public readonly string $statut        = '',
        public readonly string $anneeScolaire = '',
        public readonly string $classeId      = '',
        public readonly string $q             = '',
        public readonly int    $page          = 1,
        public readonly int    $perPage       = 25,
    ) {}

    public static function fromRequest(array $get): self
    {
        $page    = max(1, (int)($get['page']     ?? 1));
        $perPage = max(5, min(100, (int)($get['per_page'] ?? 25)));

        return new self(
            statut:        trim($get['statut']         ?? ''),
            anneeScolaire: trim($get['annee_scolaire'] ?? ''),
            classeId:      trim($get['classe_id']      ?? ''),
            q:             trim($get['q']              ?? ''),
            page:          $page,
            perPage:       $perPage,
        );
    }

    public function toArray(): array
    {
        return [
            'statut'         => $this->statut,
            'annee_scolaire' => $this->anneeScolaire,
            'classe_id'      => $this->classeId,
            'q'              => $this->q,
        ];
    }
}

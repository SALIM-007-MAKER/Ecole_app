<?php

namespace App\Modules\Academique\DTO;

class PeriodeScolaireFiltersDTO
{
    public function __construct(
        public readonly string $anneeScolaire,
        public readonly string $statut,
        public readonly string $typePeriode,
        public readonly int    $page,
        public readonly int    $perPage,
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            anneeScolaire: trim($data['annee_scolaire'] ?? ''),
            statut:        trim($data['statut']         ?? ''),
            typePeriode:   trim($data['type_periode']   ?? ''),
            page:          max(1, (int)($data['page']     ?? 1)),
            perPage:       in_array((int)($data['per_page'] ?? 20), [10, 20, 50], true)
                           ? (int)$data['per_page'] : 20,
        );
    }

    public function toArray(): array
    {
        return [
            'annee_scolaire' => $this->anneeScolaire,
            'statut'         => $this->statut,
            'type_periode'   => $this->typePeriode,
        ];
    }
}

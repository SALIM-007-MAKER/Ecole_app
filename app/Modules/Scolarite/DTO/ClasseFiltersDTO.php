<?php

namespace App\Modules\Scolarite\DTO;

class ClasseFiltersDTO
{
    public function __construct(
        public readonly string $q             = '',
        public readonly string $niveau        = '',
        public readonly string $anneeScolaire = '',
        public readonly int    $page          = 1,
        public readonly int    $perPage       = 25,
    ) {}

    public static function fromRequest(array $get): self
    {
        $page    = max(1, (int)($get['page']     ?? 1));
        $perPage = max(5, min(100, (int)($get['per_page'] ?? 25)));

        return new self(
            q:             trim($get['q']              ?? ''),
            niveau:        trim($get['niveau']         ?? ''),
            anneeScolaire: trim($get['annee_scolaire'] ?? ''),
            page:          $page,
            perPage:       $perPage,
        );
    }

    public function toArray(): array
    {
        return [
            'q'              => $this->q,
            'niveau'         => $this->niveau,
            'annee_scolaire' => $this->anneeScolaire,
        ];
    }
}

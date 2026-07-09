<?php

namespace App\Modules\Scolarite\DTO;

class EleveFiltersDTO
{
    public function __construct(
        public readonly string $q        = '',
        public readonly string $classeId = '',
        public readonly string $sexe     = '',
        public readonly string $actif    = '1',
        public readonly int    $page     = 1,
        public readonly int    $perPage  = 25,
    ) {}

    public static function fromRequest(array $get): self
    {
        return new self(
            q:        trim($get['q'] ?? ''),
            classeId: $get['classe_id'] ?? '',
            sexe:     $get['sexe'] ?? '',
            actif:    $get['actif'] ?? '1',
            page:     max(1, (int)($get['page'] ?? 1)),
            perPage:  min(100, max(10, (int)($get['per_page'] ?? 25))),
        );
    }

    public function toArray(): array
    {
        return [
            'q'         => $this->q,
            'classe_id' => $this->classeId,
            'sexe'      => $this->sexe,
            'actif'     => $this->actif,
        ];
    }
}

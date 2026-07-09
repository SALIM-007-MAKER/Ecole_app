<?php

namespace App\Modules\Academique\DTO;

class TypeEvaluationFiltersDTO
{
    public function __construct(
        public readonly string $search,
        public readonly string $actif,
        public readonly string $systeme,
        public readonly int    $page,
        public readonly int    $perPage,
    ) {}

    public static function fromRequest(array $data): self
    {
        $perPage = (int)($data['per_page'] ?? 20);
        if (!in_array($perPage, [10, 20, 50], true)) {
            $perPage = 20;
        }

        return new self(
            search:  trim($data['search']  ?? ''),
            actif:   trim($data['actif']   ?? ''),
            systeme: trim($data['systeme'] ?? ''),
            page:    max(1, (int)($data['page'] ?? 1)),
            perPage: $perPage,
        );
    }

    public function toArray(): array
    {
        return [
            'search'  => $this->search,
            'actif'   => $this->actif,
            'systeme' => $this->systeme,
        ];
    }
}

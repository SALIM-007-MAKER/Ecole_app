<?php

declare(strict_types=1);

namespace App\Modules\RH\Affectations\DTO;

readonly class AssignmentFiltersDTO
{
    public function __construct(
        public string $q             = '',
        public string $type          = '',
        public string $statut        = '',
        public ?int   $employeId     = null,
        public ?int   $departementId = null,
        public ?int   $posteId       = null,
        public bool   $includeArch   = false,
        public int    $page          = 1,
        public int    $perPage       = 20,
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            q:             trim($data['q'] ?? ''),
            type:          $data['type']   ?? '',
            statut:        $data['statut'] ?? '',
            employeId:     ($data['employe_id']     ?? '') !== '' ? (int)$data['employe_id']     : null,
            departementId: ($data['departement_id'] ?? '') !== '' ? (int)$data['departement_id'] : null,
            posteId:       ($data['poste_id']       ?? '') !== '' ? (int)$data['poste_id']       : null,
            includeArch:   !empty($data['include_arch']),
            page:          max(1, (int)($data['page'] ?? 1)),
            perPage:       20,
        );
    }

    public function offset(): int
    {
        return ($this->page - 1) * $this->perPage;
    }
}

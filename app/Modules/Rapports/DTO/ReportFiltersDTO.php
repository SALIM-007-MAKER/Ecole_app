<?php
declare(strict_types=1);

namespace App\Modules\Rapports\DTO;

class ReportFiltersDTO
{
    public function __construct(
        public readonly string  $domaine,
        public readonly string  $periode,
        public readonly string  $dateDebut,
        public readonly string  $dateFin,
        public readonly string  $typeExport,
        public readonly ?int    $classeId       = null,
        public readonly ?int    $periodeId      = null,
        public readonly ?int    $userId         = null,
        public readonly ?string $groupBy        = null,
        public readonly int     $page           = 1,
        public readonly int     $perPage        = 50,
    ) {}

    public static function fromRequest(array $data): self
    {
        $now = date('Y-m');
        return new self(
            domaine:    trim($data['domaine']     ?? 'general'),
            periode:    trim($data['periode']     ?? $now),
            dateDebut:  trim($data['date_debut']  ?? date('Y-m-01')),
            dateFin:    trim($data['date_fin']    ?? date('Y-m-t')),
            typeExport: trim($data['type_export'] ?? 'pdf'),
            classeId:   isset($data['classe_id'])  ? (int)$data['classe_id']  : null,
            periodeId:  isset($data['periode_id']) ? (int)$data['periode_id'] : null,
            userId:     isset($data['user_id'])    ? (int)$data['user_id']    : null,
            groupBy:    $data['group_by'] ?? null,
            page:       max(1, (int)($data['page']     ?? 1)),
            perPage:    min(200, max(10, (int)($data['per_page'] ?? 50))),
        );
    }

    public function toArray(): array
    {
        return [
            'domaine'     => $this->domaine,
            'periode'     => $this->periode,
            'date_debut'  => $this->dateDebut,
            'date_fin'    => $this->dateFin,
            'type_export' => $this->typeExport,
            'classe_id'   => $this->classeId,
            'periode_id'  => $this->periodeId,
            'user_id'     => $this->userId,
            'group_by'    => $this->groupBy,
        ];
    }
}

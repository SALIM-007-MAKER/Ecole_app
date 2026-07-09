<?php

declare(strict_types=1);

namespace App\Modules\Inventaire\DTO;

class MaintenanceDTO
{
    public function __construct(
        public readonly int     $articleId,
        public readonly string  $type,
        public readonly string  $datePlanifiee,
        public readonly ?string $prestataire,
        public readonly ?float  $cout,
        public readonly ?string $description,
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            articleId:     (int)($data['article_id'] ?? 0),
            type:          $data['type'] ?? 'preventive',
            datePlanifiee: $data['date_planifiee'] ?? date('Y-m-d'),
            prestataire:   $data['prestataire'] ?? null,
            cout:          isset($data['cout']) ? (float)$data['cout'] : null,
            description:   $data['description'] ?? null,
        );
    }
}

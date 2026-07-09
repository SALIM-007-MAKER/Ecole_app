<?php
declare(strict_types=1);

namespace App\Modules\Portals\DTO;

class DashboardDTO
{
    public function __construct(
        public readonly string $portal,
        public readonly string $titre,
        /** @var WidgetDataDTO[] */
        public readonly array  $widgets,
        public readonly array  $alertes,
        public readonly array  $layout,
        public readonly array  $metadata = [],
    ) {}

    public function toArray(): array
    {
        return [
            'portal'   => $this->portal,
            'titre'    => $this->titre,
            'widgets'  => array_map(fn($w) => $w->toArray(), $this->widgets),
            'alertes'  => $this->alertes,
            'layout'   => $this->layout,
            'metadata' => $this->metadata,
        ];
    }
}

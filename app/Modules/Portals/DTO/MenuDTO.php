<?php
declare(strict_types=1);

namespace App\Modules\Portals\DTO;

class MenuDTO
{
    public function __construct(
        public readonly string $portal,
        /** @var MenuItemDTO[] */
        public readonly array  $items,
        public readonly array  $breadcrumbs = [],
    ) {}

    public function toArray(): array
    {
        return [
            'portal'      => $this->portal,
            'items'       => array_map(fn($i) => $i->toArray(), $this->items),
            'breadcrumbs' => $this->breadcrumbs,
        ];
    }
}

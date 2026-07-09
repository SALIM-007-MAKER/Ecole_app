<?php
declare(strict_types=1);

namespace App\Modules\Portals\DTO;

class MenuItemDTO
{
    public function __construct(
        public readonly string  $id,
        public readonly string  $label,
        public readonly string  $icon,
        public readonly string  $url,
        public readonly ?string $permission,
        public readonly ?int    $badge,
        public readonly bool    $active,
        /** @var MenuItemDTO[] */
        public readonly array   $children,
        public readonly bool    $separator = false,
        public readonly string  $target    = '_self',
    ) {}

    public function toArray(): array
    {
        return [
            'id'         => $this->id,
            'label'      => $this->label,
            'icon'       => $this->icon,
            'url'        => $this->url,
            'permission' => $this->permission,
            'badge'      => $this->badge,
            'active'     => $this->active,
            'children'   => array_map(fn($c) => $c->toArray(), $this->children),
            'separator'  => $this->separator,
            'target'     => $this->target,
        ];
    }
}

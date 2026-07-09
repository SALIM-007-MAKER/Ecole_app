<?php
declare(strict_types=1);

namespace App\Modules\Portals\DTO;

class ShortcutDTO
{
    public function __construct(
        public readonly int    $id,
        public readonly string $label,
        public readonly string $url,
        public readonly string $icon,
        public readonly string $color,
        public readonly int    $ordre,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id:    (int)($data['id']    ?? 0),
            label: (string)($data['label'] ?? ''),
            url:   (string)($data['url']   ?? '#'),
            icon:  (string)($data['icon']  ?? 'link'),
            color: (string)($data['color'] ?? 'violet'),
            ordre: (int)($data['ordre']    ?? 0),
        );
    }

    public function toArray(): array
    {
        return [
            'id'    => $this->id,
            'label' => $this->label,
            'url'   => $this->url,
            'icon'  => $this->icon,
            'color' => $this->color,
            'ordre' => $this->ordre,
        ];
    }
}

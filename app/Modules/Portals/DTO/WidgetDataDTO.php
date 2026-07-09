<?php
declare(strict_types=1);

namespace App\Modules\Portals\DTO;

class WidgetDataDTO
{
    public function __construct(
        public readonly string  $id,
        public readonly string  $titre,
        public readonly string  $icon,
        public readonly string  $taille,
        public readonly int     $ordre,
        public readonly bool    $refreshable,
        public readonly int     $refreshInterval,
        public readonly string  $template,
        public readonly array   $data,
        public readonly ?string $cachedUntil = null,
    ) {}

    public function toArray(): array
    {
        return [
            'id'               => $this->id,
            'titre'            => $this->titre,
            'icon'             => $this->icon,
            'taille'           => $this->taille,
            'ordre'            => $this->ordre,
            'refreshable'      => $this->refreshable,
            'refresh_interval' => $this->refreshInterval,
            'template'         => $this->template,
            'data'             => $this->data,
            'cached_until'     => $this->cachedUntil,
        ];
    }
}

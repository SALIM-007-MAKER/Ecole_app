<?php
declare(strict_types=1);

namespace App\Modules\Portals\Shared\Widgets;

use App\Modules\Portals\Contracts\WidgetInterface;

abstract class BaseWidget implements WidgetInterface
{
    public function getPermissions(): array
    {
        return [];
    }

    public function getDefaultSize(): string
    {
        return 'md';
    }

    public function getDefaultOrder(): int
    {
        return 10;
    }

    public function isRefreshable(): bool
    {
        return false;
    }

    public function getRefreshInterval(): int
    {
        return 0;
    }

    /** Portails qui peuvent afficher ce widget (vide = tous) */
    public function getPortals(): array
    {
        return ['admin', 'direction', 'enseignant', 'eleve', 'parent', 'comptabilite', 'rh'];
    }
}

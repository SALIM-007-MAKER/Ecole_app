<?php
declare(strict_types=1);

namespace App\Modules\Portals\Framework;

use App\Modules\Portals\Contracts\WidgetInterface;

/**
 * Registre statique des widgets — même pattern que EventDispatcher.
 * Peuplé une fois par PortalBootstrap::boot() à chaque requête.
 */
class WidgetRegistry
{
    private static array $widgets = [];

    public static function register(WidgetInterface $widget): void
    {
        self::$widgets[$widget->getId()] = $widget;
    }

    public static function reset(): void
    {
        self::$widgets = [];
    }

    public function get(string $id): ?WidgetInterface
    {
        return self::$widgets[$id] ?? null;
    }

    public function getAll(): array
    {
        return self::$widgets;
    }

    public function getForPortal(string $portal): array
    {
        return array_values(array_filter(
            self::$widgets,
            fn(WidgetInterface $w) => in_array($portal, $w->getPortals(), true)
        ));
    }

    /** Filtre par portail ET par permissions utilisateur */
    public function getAvailable(string $portal, array $perms): array
    {
        return array_values(array_filter(
            $this->getForPortal($portal),
            function (WidgetInterface $w) use ($perms): bool {
                foreach ($w->getPermissions() as $perm) {
                    if (!in_array($perm, $perms, true)) {
                        return false;
                    }
                }
                return true;
            }
        ));
    }
}

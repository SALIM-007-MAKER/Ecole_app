<?php
declare(strict_types=1);

namespace App\Modules\Portals\Framework;

class PortalThemeManager
{
    private const PORTAL_COLORS = [
        'admin'        => ['primary' => '#7c3aed', 'light' => '#ede9fe', 'ring' => 'ring-violet-500',   'bg' => 'bg-violet-600',  'text' => 'text-violet-600',  'name' => 'violet'],
        'direction'    => ['primary' => '#4338ca', 'light' => '#e0e7ff', 'ring' => 'ring-indigo-500',   'bg' => 'bg-indigo-600',  'text' => 'text-indigo-600',  'name' => 'indigo'],
        'enseignant'   => ['primary' => '#0d9488', 'light' => '#ccfbf1', 'ring' => 'ring-teal-500',     'bg' => 'bg-teal-600',    'text' => 'text-teal-600',    'name' => 'teal'],
        'eleve'        => ['primary' => '#2563eb', 'light' => '#dbeafe', 'ring' => 'ring-blue-500',     'bg' => 'bg-blue-600',    'text' => 'text-blue-600',    'name' => 'blue'],
        'parent'       => ['primary' => '#059669', 'light' => '#d1fae5', 'ring' => 'ring-emerald-500',  'bg' => 'bg-emerald-600', 'text' => 'text-emerald-600', 'name' => 'emerald'],
        'comptabilite' => ['primary' => '#d97706', 'light' => '#fef3c7', 'ring' => 'ring-amber-500',    'bg' => 'bg-amber-600',   'text' => 'text-amber-600',   'name' => 'amber'],
        'rh'           => ['primary' => '#e11d48', 'light' => '#ffe4e6', 'ring' => 'ring-rose-500',     'bg' => 'bg-rose-600',    'text' => 'text-rose-600',    'name' => 'rose'],
    ];

    private const THEMES = [
        'default' => ['density' => 'normal',  'radius' => 'rounded-lg',  'shadow' => 'shadow-sm'],
        'compact' => ['density' => 'compact', 'radius' => 'rounded',     'shadow' => 'shadow'],
        'comfort' => ['density' => 'comfort', 'radius' => 'rounded-xl',  'shadow' => 'shadow-md'],
    ];

    public function getPortalColor(string $portal): array
    {
        return self::PORTAL_COLORS[$portal] ?? self::PORTAL_COLORS['admin'];
    }

    public function getThemeClasses(string $theme): array
    {
        return self::THEMES[$theme] ?? self::THEMES['default'];
    }

    public function getAvailableThemes(): array
    {
        return array_keys(self::THEMES);
    }

    public function getCssVars(string $portal, string $theme = 'default'): string
    {
        $color = $this->getPortalColor($portal);
        return sprintf(
            ':root { --portal-primary: %s; --portal-light: %s; }',
            $color['primary'],
            $color['light']
        );
    }
}

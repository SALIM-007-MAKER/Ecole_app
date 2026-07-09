<?php
declare(strict_types=1);

namespace App\Modules\Portals\Framework;

use App\Modules\Portals\DTO\WidgetDataDTO;

class PortalLayoutManager
{
    private const SIZE_CLASSES = [
        'xs'  => 'col-span-12 sm:col-span-6 lg:col-span-3',
        'sm'  => 'col-span-12 sm:col-span-6 lg:col-span-4',
        'md'  => 'col-span-12 lg:col-span-6',
        'lg'  => 'col-span-12 lg:col-span-8',
        'xl'  => 'col-span-12',
    ];

    public function getColumnClass(string $size): string
    {
        return self::SIZE_CLASSES[$size] ?? self::SIZE_CLASSES['md'];
    }

    /** Applique le layout sauvegardé à la liste de widgets (réordonne + resize) */
    public function applyLayout(array $widgetDataList, array $savedLayout): array
    {
        if (empty($savedLayout)) {
            return $widgetDataList;
        }
        $result = [];
        foreach ($widgetDataList as $widget) {
            $saved = $savedLayout[$widget->id] ?? null;
            if ($saved !== null) {
                $widget = new WidgetDataDTO(
                    id:              $widget->id,
                    titre:           $widget->titre,
                    icon:            $widget->icon,
                    taille:          (string)($saved['taille'] ?? $widget->taille),
                    ordre:           (int)($saved['ordre']    ?? $widget->ordre),
                    refreshable:     $widget->refreshable,
                    refreshInterval: $widget->refreshInterval,
                    template:        $widget->template,
                    data:            $widget->data,
                    cachedUntil:     $widget->cachedUntil,
                );
            }
            $result[] = $widget;
        }
        usort($result, fn($a, $b) => $a->ordre <=> $b->ordre);
        return $result;
    }

    /** Normalise le layout avant sauvegarde */
    public function normalizeLayout(array $rawLayout): array
    {
        $normalized = [];
        foreach ($rawLayout as $widgetId => $config) {
            $normalized[$widgetId] = [
                'ordre'  => max(0, (int)($config['ordre']  ?? 0)),
                'taille' => in_array($config['taille'] ?? '', ['xs','sm','md','lg','xl'], true)
                    ? $config['taille']
                    : 'md',
                'visible' => (bool)($config['visible'] ?? true),
            ];
        }
        return $normalized;
    }

    public function getAvailableSizes(): array
    {
        return array_keys(self::SIZE_CLASSES);
    }
}

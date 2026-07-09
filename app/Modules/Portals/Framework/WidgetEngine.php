<?php
declare(strict_types=1);

namespace App\Modules\Portals\Framework;

use App\Modules\Portals\DTO\WidgetDataDTO;
use App\Modules\Portals\Repositories\WidgetCacheRepository;

class WidgetEngine
{
    public function __construct(
        private readonly WidgetRegistry        $registry,
        private readonly WidgetCacheRepository $cache,
    ) {}

    public function render(string $widgetId, int $etablissementId, int $userId, array $config = []): WidgetDataDTO
    {
        $widget = $this->registry->get($widgetId);
        if ($widget === null) {
            throw new \InvalidArgumentException("Widget introuvable : {$widgetId}");
        }

        if ($widget->getRefreshInterval() > 0) {
            $cached = $this->cache->get($widgetId, '', $etablissementId, $userId);
            $data   = $cached !== null
                ? $cached['data']
                : $this->fetchAndCache($widget, $etablissementId, $userId, $config);
        } else {
            $data = $this->safeGetData($widget, $etablissementId, $userId, $config);
        }

        return new WidgetDataDTO(
            id:              $widget->getId(),
            titre:           $widget->getTitle(),
            icon:            $widget->getIcon(),
            taille:          (string)($config['taille'] ?? $widget->getDefaultSize()),
            ordre:           (int)($config['ordre']    ?? $widget->getDefaultOrder()),
            refreshable:     $widget->isRefreshable(),
            refreshInterval: $widget->getRefreshInterval(),
            template:        $widget->getTemplate(),
            data:            $data,
        );
    }

    private function fetchAndCache($widget, int $etab, int $userId, array $config): array
    {
        $data = $this->safeGetData($widget, $etab, $userId, $config);
        $this->cache->set($widget->getId(), '', $etab, $data, $widget->getRefreshInterval(), $userId);
        return $data;
    }

    private function safeGetData($widget, int $etab, int $userId, array $config): array
    {
        try {
            return $widget->getData($etab, $userId, $config);
        } catch (\Throwable $e) {
            error_log('[WidgetEngine] ' . $widget->getId() . ': ' . $e->getMessage());
            return ['error' => true, 'message' => 'Données temporairement indisponibles'];
        }
    }
}

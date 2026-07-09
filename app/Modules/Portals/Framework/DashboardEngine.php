<?php
declare(strict_types=1);

namespace App\Modules\Portals\Framework;

use App\Modules\Portals\Contracts\WidgetInterface;
use App\Modules\Portals\DTO\DashboardDTO;
use App\Modules\Portals\DTO\WidgetDataDTO;
use App\Modules\Portals\Repositories\WidgetCacheRepository;

class DashboardEngine
{
    public function __construct(
        private readonly WidgetRegistry        $registry,
        private readonly UserPreferenceService $preferences,
        private readonly WidgetCacheRepository $cache,
    ) {}

    public function build(string $portal, int $etablissementId, int $userId, array $perms): DashboardDTO
    {
        $widgets     = $this->registry->getAvailable($portal, $perms);
        $savedLayout = $this->preferences->getWidgetLayout($userId, $portal, $etablissementId);
        $hiddenIds   = $this->preferences->getHiddenWidgets($userId, $portal, $etablissementId);

        // Exclure les widgets cachés par l'utilisateur
        $widgets = array_filter($widgets, fn($w) => !in_array($w->getId(), $hiddenIds, true));

        $widgetData = [];
        foreach ($widgets as $widget) {
            $widgetData[] = $this->renderWidget($widget, $etablissementId, $userId, $savedLayout);
        }

        // Trier par ordre personnalisé puis par ordre par défaut
        usort($widgetData, fn($a, $b) => $a->ordre <=> $b->ordre);

        return new DashboardDTO(
            portal:   $portal,
            titre:    $this->getPortalTitle($portal),
            widgets:  $widgetData,
            alertes:  [],
            layout:   $savedLayout,
            metadata: ['portal' => $portal, 'etab_id' => $etablissementId, 'user_id' => $userId],
        );
    }

    public function renderWidget(
        WidgetInterface $widget,
        int $etablissementId,
        int $userId,
        array $savedLayout = []
    ): WidgetDataDTO {
        $config = $savedLayout[$widget->getId()] ?? [];

        if ($widget->getRefreshInterval() > 0) {
            $cached = $this->cache->get($widget->getId(), '', $etablissementId, $userId);
            if ($cached !== null) {
                $data        = $cached['data'];
                $cachedUntil = $cached['expires_at'];
            } else {
                $data = $this->safeGetData($widget, $etablissementId, $userId, $config);
                $this->cache->set($widget->getId(), '', $etablissementId, $data, $widget->getRefreshInterval(), $userId);
                $cachedUntil = null;
            }
        } else {
            $data        = $this->safeGetData($widget, $etablissementId, $userId, $config);
            $cachedUntil = null;
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
            cachedUntil:     $cachedUntil,
        );
    }

    private function safeGetData(WidgetInterface $widget, int $etab, int $userId, array $config): array
    {
        try {
            return $widget->getData($etab, $userId, $config);
        } catch (\Throwable $e) {
            error_log('[DashboardEngine] Widget ' . $widget->getId() . ': ' . $e->getMessage());
            return ['error' => true, 'message' => 'Données temporairement indisponibles'];
        }
    }

    private function getPortalTitle(string $portal): string
    {
        return match($portal) {
            'admin'        => 'Administration',
            'direction'    => 'Direction',
            'enseignant'   => 'Espace Enseignant',
            'eleve'        => 'Espace Élève',
            'parent'       => 'Espace Parent',
            'comptabilite' => 'Comptabilité',
            'rh'           => 'Ressources Humaines',
            default        => ucfirst($portal),
        };
    }
}

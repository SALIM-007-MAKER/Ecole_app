<?php
declare(strict_types=1);

namespace App\Modules\Portals\Framework;

use App\Modules\Portals\DTO\PreferencesDTO;
use App\Modules\Portals\Events\PreferencesSaved;
use App\Modules\Portals\Repositories\PreferencesRepository;
use Core\EventDispatcher;

class UserPreferenceService
{
    public function __construct(
        private readonly PreferencesRepository $repo,
    ) {}

    public function get(int $userId, string $portal, int $etablissementId): PreferencesDTO
    {
        $row = $this->repo->find($userId, $portal, $etablissementId);
        if ($row === null) {
            return PreferencesDTO::defaults();
        }
        return PreferencesDTO::fromArray([
            'widget_layout'  => json_decode($row['widget_layout']  ?? '[]', true) ?? [],
            'hidden_widgets' => json_decode($row['hidden_widgets'] ?? '[]', true) ?? [],
            'shortcuts'      => json_decode($row['shortcuts']      ?? '[]', true) ?? [],
            'theme'          => $row['theme']        ?? 'default',
            'default_page'   => $row['default_page'] ?? '',
            'notif_prefs'    => json_decode($row['notif_prefs']    ?? '[]', true) ?? [],
            'lang'           => $row['lang']         ?? 'fr',
        ]);
    }

    public function save(int $userId, string $portal, int $etablissementId, array $data): void
    {
        $allowed = ['theme', 'default_page', 'lang', 'notif_prefs'];
        $fields  = array_intersect_key($data, array_flip($allowed));

        if (isset($fields['notif_prefs']) && is_array($fields['notif_prefs'])) {
            $fields['notif_prefs'] = json_encode($fields['notif_prefs']);
        }

        $this->repo->upsert($userId, $portal, $etablissementId, $fields);
        EventDispatcher::dispatch(new PreferencesSaved($portal, $userId, $etablissementId));
    }

    public function getWidgetLayout(int $userId, string $portal, int $etablissementId): array
    {
        $row = $this->repo->find($userId, $portal, $etablissementId);
        if ($row === null || empty($row['widget_layout'])) {
            return [];
        }
        return json_decode($row['widget_layout'], true) ?? [];
    }

    public function saveWidgetLayout(int $userId, string $portal, int $etablissementId, array $layout): void
    {
        $this->repo->upsertField($userId, $portal, $etablissementId, 'widget_layout', json_encode($layout));
        EventDispatcher::dispatch(new PreferencesSaved($portal, $userId, $etablissementId));
    }

    public function getHiddenWidgets(int $userId, string $portal, int $etablissementId): array
    {
        $row = $this->repo->find($userId, $portal, $etablissementId);
        if ($row === null || empty($row['hidden_widgets'])) {
            return [];
        }
        return json_decode($row['hidden_widgets'], true) ?? [];
    }

    public function toggleWidget(int $userId, string $portal, int $etablissementId, string $widgetId): bool
    {
        $hidden = $this->getHiddenWidgets($userId, $portal, $etablissementId);
        if (in_array($widgetId, $hidden, true)) {
            $hidden = array_values(array_filter($hidden, fn($id) => $id !== $widgetId));
            $visible = true;
        } else {
            $hidden[] = $widgetId;
            $visible  = false;
        }
        $this->repo->upsertField($userId, $portal, $etablissementId, 'hidden_widgets', json_encode($hidden));
        return $visible;
    }

    public function reset(int $userId, string $portal, int $etablissementId): void
    {
        $this->repo->delete($userId, $portal, $etablissementId);
    }
}

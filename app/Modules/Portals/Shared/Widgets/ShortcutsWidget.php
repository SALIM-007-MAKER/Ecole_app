<?php
declare(strict_types=1);

namespace App\Modules\Portals\Shared\Widgets;

use App\Modules\Portals\Framework\ShortcutEngine;
use App\Modules\Portals\Repositories\PreferencesRepository;

class ShortcutsWidget extends BaseWidget
{
    public function getId(): string { return 'shared_shortcuts'; }

    public function getTitle(): string { return 'Raccourcis rapides'; }

    public function getIcon(): string { return 'zap'; }

    public function getDefaultSize(): string { return 'sm'; }

    public function getDefaultOrder(): int { return 3; }

    public function getTemplate(): string { return 'Portals::Shared/Widgets/shortcuts'; }

    public function getData(int $etablissementId, int $userId, array $config = []): array
    {
        $portal = $config['portal'] ?? 'admin';
        $engine = new ShortcutEngine(new PreferencesRepository());
        $shortcuts = $engine->getUserShortcuts($userId, $portal, $etablissementId);

        return [
            'shortcuts'    => array_map(fn($s) => $s->toArray(), $shortcuts),
            'edit_url'     => '/v2/portals/' . $portal . '/preferences/raccourcis',
        ];
    }
}

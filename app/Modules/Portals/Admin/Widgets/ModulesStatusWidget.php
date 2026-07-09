<?php
declare(strict_types=1);

namespace App\Modules\Portals\Admin\Widgets;

use App\Modules\Portals\Shared\Widgets\BaseWidget;

class ModulesStatusWidget extends BaseWidget
{
    public function getId(): string    { return 'admin_modules_status'; }
    public function getTitle(): string { return 'État des modules'; }
    public function getIcon(): string  { return 'grid'; }
    public function getPortals(): array { return ['admin']; }
    public function getPermissions(): array { return ['users.view']; }
    public function getDefaultSize(): string { return 'md'; }
    public function getDefaultOrder(): int   { return 2; }
    public function getTemplate(): string { return 'Portals::Shared/Widgets/admin/modules_status'; }

    public function getData(int $etab, int $userId, array $config): array
    {
        try {
            $modules = require ROOT_PATH . '/config/modules.php';
            $status  = [];
            foreach ($modules as $key => $mod) {
                $status[] = [
                    'key'     => $key,
                    'enabled' => (bool)($mod['enabled'] ?? false),
                ];
            }
            return ['modules' => $status, 'total' => count($status), 'enabled' => count(array_filter($status, fn($m) => $m['enabled']))];
        } catch (\Throwable) {
            return ['modules' => [], 'total' => 0, 'enabled' => 0];
        }
    }
}

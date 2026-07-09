<?php
declare(strict_types=1);

namespace App\Modules\Portals\Admin\Widgets;

use App\Modules\Portals\Shared\Widgets\BaseWidget;
use Core\Database;

class SessionsActivesWidget extends BaseWidget
{
    public function getId(): string    { return 'admin_sessions_actives'; }
    public function getTitle(): string { return 'Sessions actives'; }
    public function getIcon(): string  { return 'log-in'; }
    public function getPortals(): array { return ['admin']; }
    public function getPermissions(): array { return ['users.view']; }
    public function getDefaultSize(): string { return 'sm'; }
    public function getDefaultOrder(): int   { return 8; }
    public function isRefreshable(): bool    { return true; }
    public function getRefreshInterval(): int { return 60; }
    public function getTemplate(): string { return 'Portals::Shared/Widgets/admin/sessions_actives'; }

    public function getData(int $etab, int $userId, array $config): array
    {
        try {
            $pdo  = Database::getInstance()->getConnection();
            $stmt = $pdo->prepare(
                'SELECT COUNT(*) AS nb, portal FROM portal_access_logs
                 WHERE etablissement_id=? AND created_at >= DATE_SUB(NOW(), INTERVAL 30 MINUTE)
                 GROUP BY portal'
            );
            $stmt->execute([$etab]);
            $byPortal = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            $total    = array_sum(array_column($byPortal, 'nb'));
            return ['by_portal' => $byPortal, 'total' => $total];
        } catch (\Throwable) {
            return ['by_portal' => [], 'total' => 0];
        }
    }
}

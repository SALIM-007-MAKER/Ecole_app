<?php
declare(strict_types=1);

namespace App\Modules\Portals\Admin\Widgets;

use App\Modules\Portals\Shared\Widgets\BaseWidget;
use Core\Database;

class AlertesSystèmeWidget extends BaseWidget
{
    public function getId(): string    { return 'admin_alertes_systeme'; }
    public function getTitle(): string { return 'Alertes système'; }
    public function getIcon(): string  { return 'alert-triangle'; }
    public function getPortals(): array { return ['admin']; }
    public function getPermissions(): array { return ['users.view']; }
    public function getDefaultSize(): string { return 'sm'; }
    public function getDefaultOrder(): int   { return 5; }
    public function isRefreshable(): bool    { return true; }
    public function getRefreshInterval(): int { return 120; }
    public function getTemplate(): string { return 'Portals::Shared/Widgets/admin/alertes_systeme'; }

    public function getData(int $etab, int $userId, array $config): array
    {
        $alertes = [];
        try {
            $pdo = Database::getInstance()->getConnection();

            $expiring = $pdo->prepare(
                'SELECT COUNT(*) FROM portal_api_tokens
                 WHERE etablissement_id=? AND expires_at < DATE_ADD(NOW(), INTERVAL 7 DAY)
                   AND expires_at > NOW()'
            );
            $expiring->execute([$etab]);
            $nbTokensExp = (int)$expiring->fetchColumn();
            if ($nbTokensExp > 0) {
                $alertes[] = ['type' => 'warning', 'message' => "$nbTokensExp token(s) API expirent dans 7 jours"];
            }
        } catch (\Throwable) {}

        return ['alertes' => $alertes, 'count' => count($alertes)];
    }
}

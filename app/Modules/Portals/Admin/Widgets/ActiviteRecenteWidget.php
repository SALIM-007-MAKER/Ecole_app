<?php
declare(strict_types=1);

namespace App\Modules\Portals\Admin\Widgets;

use App\Modules\Portals\Shared\Widgets\BaseWidget;
use Core\Database;

class ActiviteRecenteWidget extends BaseWidget
{
    public function getId(): string    { return 'admin_activite_recente'; }
    public function getTitle(): string { return 'Activité récente'; }
    public function getIcon(): string  { return 'activity'; }
    public function getPortals(): array { return ['admin']; }
    public function getPermissions(): array { return ['users.view']; }
    public function getDefaultSize(): string { return 'md'; }
    public function getDefaultOrder(): int   { return 3; }
    public function isRefreshable(): bool    { return true; }
    public function getRefreshInterval(): int { return 60; }
    public function getTemplate(): string { return 'Portals::Shared/Widgets/admin/activite_recente'; }

    public function getData(int $etab, int $userId, array $config): array
    {
        try {
            $pdo  = Database::getInstance()->getConnection();
            $stmt = $pdo->prepare(
                'SELECT al.action, al.module, al.created_at,
                        CONCAT(u.prenom," ",u.nom) AS user_nom
                 FROM audit_logs al
                 LEFT JOIN users u ON u.id = al.user_id
                 ORDER BY al.created_at DESC LIMIT 10'
            );
            $stmt->execute();
            return ['logs' => $stmt->fetchAll(\PDO::FETCH_ASSOC)];
        } catch (\Throwable) {
            return ['logs' => []];
        }
    }
}

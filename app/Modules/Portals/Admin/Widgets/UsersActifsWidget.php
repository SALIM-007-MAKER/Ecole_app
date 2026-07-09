<?php
declare(strict_types=1);

namespace App\Modules\Portals\Admin\Widgets;

use App\Modules\Portals\Shared\Widgets\BaseWidget;
use Core\Database;

class UsersActifsWidget extends BaseWidget
{
    public function getId(): string    { return 'admin_users_actifs'; }
    public function getTitle(): string { return 'Utilisateurs actifs'; }
    public function getIcon(): string  { return 'users'; }
    public function getPortals(): array { return ['admin']; }
    public function getPermissions(): array { return ['users.view']; }
    public function getDefaultSize(): string { return 'sm'; }
    public function getDefaultOrder(): int   { return 4; }
    public function isRefreshable(): bool    { return true; }
    public function getRefreshInterval(): int { return 120; }
    public function getTemplate(): string { return 'Portals::Shared/Widgets/admin/users_actifs'; }

    public function getData(int $etab, int $userId, array $config): array
    {
        try {
            $pdo  = Database::getInstance()->getConnection();
            $stmt = $pdo->prepare(
                'SELECT role, COUNT(*) AS nb
                 FROM users
                 WHERE etablissement_id=? AND deleted_at IS NULL AND statut="actif"
                 GROUP BY role ORDER BY nb DESC'
            );
            $stmt->execute([$etab]);
            $byRole = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $total = array_sum(array_column($byRole, 'nb'));
            return ['by_role' => $byRole, 'total' => $total];
        } catch (\Throwable) {
            return ['by_role' => [], 'total' => 0];
        }
    }
}

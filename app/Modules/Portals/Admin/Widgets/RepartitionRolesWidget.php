<?php
declare(strict_types=1);

namespace App\Modules\Portals\Admin\Widgets;

use App\Modules\Portals\Shared\Widgets\BaseWidget;
use Core\Database;

class RepartitionRolesWidget extends BaseWidget
{
    public function getId(): string    { return 'admin_repartition_roles'; }
    public function getTitle(): string { return 'Répartition des rôles'; }
    public function getIcon(): string  { return 'pie-chart'; }
    public function getPortals(): array { return ['admin']; }
    public function getPermissions(): array { return ['users.view']; }
    public function getDefaultSize(): string { return 'sm'; }
    public function getDefaultOrder(): int   { return 6; }
    public function getTemplate(): string { return 'Portals::Shared/Widgets/admin/repartition_roles'; }

    public function getData(int $etab, int $userId, array $config): array
    {
        try {
            $pdo  = Database::getInstance()->getConnection();
            $stmt = $pdo->prepare(
                'SELECT role, COUNT(*) AS nb FROM users
                 WHERE etablissement_id=? AND deleted_at IS NULL GROUP BY role'
            );
            $stmt->execute([$etab]);
            return ['repartition' => $stmt->fetchAll(\PDO::FETCH_ASSOC)];
        } catch (\Throwable) {
            return ['repartition' => []];
        }
    }
}

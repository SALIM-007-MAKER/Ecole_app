<?php
declare(strict_types=1);

namespace App\Modules\Portals\Admin\Widgets;

use App\Modules\Portals\Shared\Widgets\BaseWidget;
use Core\Database;

class CapaciteWidget extends BaseWidget
{
    public function getId(): string    { return 'admin_capacite'; }
    public function getTitle(): string { return 'Capacité d\'accueil'; }
    public function getIcon(): string  { return 'home'; }
    public function getPortals(): array { return ['admin']; }
    public function getPermissions(): array { return ['users.view']; }
    public function getDefaultSize(): string { return 'sm'; }
    public function getDefaultOrder(): int   { return 7; }
    public function getTemplate(): string { return 'Portals::Shared/Widgets/admin/capacite'; }

    public function getData(int $etab, int $userId, array $config): array
    {
        try {
            $pdo  = Database::getInstance()->getConnection();
            $stmt = $pdo->prepare(
                'SELECT c.nom, c.capacite_max, COUNT(e.id) AS nb_inscrits
                 FROM classes c
                 LEFT JOIN eleves e ON e.classe_id = c.id AND e.deleted_at IS NULL
                 WHERE c.etablissement_id=? AND c.deleted_at IS NULL
                 GROUP BY c.id ORDER BY c.niveau, c.nom'
            );
            $stmt->execute([$etab]);
            $classes = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            return ['classes' => $classes, 'total_classes' => count($classes)];
        } catch (\Throwable) {
            return ['classes' => [], 'total_classes' => 0];
        }
    }
}

<?php
declare(strict_types=1);

namespace App\Modules\Portals\Admin\Widgets;

use App\Modules\Portals\Shared\Widgets\BaseWidget;
use Core\Database;

class StatsGlobalesWidget extends BaseWidget
{
    public function getId(): string    { return 'admin_stats_globales'; }
    public function getTitle(): string { return 'Statistiques globales'; }
    public function getIcon(): string  { return 'bar-chart-2'; }
    public function getPortals(): array { return ['admin']; }
    public function getPermissions(): array { return ['users.view']; }
    public function getDefaultSize(): string { return 'lg'; }
    public function getDefaultOrder(): int   { return 1; }
    public function isRefreshable(): bool    { return true; }
    public function getRefreshInterval(): int { return 300; }
    public function getTemplate(): string { return 'Portals::Shared/Widgets/admin/stats_globales'; }

    public function getData(int $etab, int $userId, array $config): array
    {
        try {
            $pdo = Database::getInstance()->getConnection();
            $nb = static fn($sql, $p) => (int)$pdo->prepare($sql)->execute($p) ? 0 : 0;

            $eleves = $pdo->prepare('SELECT COUNT(*) FROM eleves WHERE etablissement_id=? AND deleted_at IS NULL');
            $eleves->execute([$etab]);

            $employes = $pdo->prepare('SELECT COUNT(*) FROM rh_employes WHERE etablissement_id=? AND statut="actif" AND deleted_at IS NULL');
            $employes->execute([$etab]);

            $classes = $pdo->prepare('SELECT COUNT(*) FROM classes WHERE etablissement_id=? AND deleted_at IS NULL');
            $classes->execute([$etab]);

            $users = $pdo->prepare('SELECT COUNT(*) FROM users WHERE etablissement_id=? AND deleted_at IS NULL');
            $users->execute([$etab]);

            return [
                'nb_eleves'   => (int)$eleves->fetchColumn(),
                'nb_employes' => (int)$employes->fetchColumn(),
                'nb_classes'  => (int)$classes->fetchColumn(),
                'nb_users'    => (int)$users->fetchColumn(),
            ];
        } catch (\Throwable) {
            return ['nb_eleves' => 0, 'nb_employes' => 0, 'nb_classes' => 0, 'nb_users' => 0];
        }
    }
}

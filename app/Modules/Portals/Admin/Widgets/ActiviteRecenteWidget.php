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

    public function getData(int $etablissementId, int $userId, array $config = []): array
    {
        try {
            // Dix derniers événements DE L'ÉTABLISSEMENT $etablissementId uniquement (établissement explicite obligatoire, jamais de lecture globale).
            $res = (new \App\Services\AuditService())->rechercher($etablissementId, [], 1, 10);
            return ['logs' => array_map(static fn(array $l) => [
                'action' => $l['action'], 'module' => $l['module'], 'created_at' => $l['created_at'], 'user_nom' => (string)($l['acteur_libelle'] ?? ''),
            ], $res['rows'])];
        } catch (\Throwable) {
            return ['logs' => []];
        }
    }
}

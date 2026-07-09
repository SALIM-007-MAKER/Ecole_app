<?php
declare(strict_types=1);

namespace App\Modules\Portals\Direction\Widgets;

use App\Modules\Portals\Shared\Widgets\BaseWidget;
use Core\Database;

class KpiAcademiqueWidget extends BaseWidget
{
    public function getId(): string    { return 'direction_kpi_academique'; }
    public function getTitle(): string { return 'KPI Académique'; }
    public function getIcon(): string  { return 'award'; }
    public function getPortals(): array { return ['direction']; }
    public function getPermissions(): array { return ['academique.bulletin.view']; }
    public function getDefaultSize(): string { return 'sm'; }
    public function getDefaultOrder(): int   { return 2; }
    public function isRefreshable(): bool    { return true; }
    public function getRefreshInterval(): int { return 600; }
    public function getTemplate(): string { return 'Portals::Shared/Widgets/direction/kpi_academique'; }

    public function getData(int $etab, int $userId, array $config): array
    {
        try {
            $pdo = Database::getInstance()->getConnection();

            $moyStmt = $pdo->prepare(
                'SELECT AVG(n.valeur) AS moyenne_generale FROM notes_v n
                 WHERE n.etablissement_id=? AND n.deleted_at IS NULL'
            );
            $moyStmt->execute([$etab]);
            $moyenne = round((float)$moyStmt->fetchColumn(), 2);

            $bulletinsStmt = $pdo->prepare(
                'SELECT COUNT(*) FROM bulletins_v2 WHERE etablissement_id=? AND deleted_at IS NULL'
            );
            $bulletinsStmt->execute([$etab]);
            $nbBulletins = (int)$bulletinsStmt->fetchColumn();

            return ['moyenne_generale' => $moyenne, 'nb_bulletins' => $nbBulletins];
        } catch (\Throwable) {
            return ['moyenne_generale' => 0, 'nb_bulletins' => 0];
        }
    }
}

<?php
declare(strict_types=1);

namespace App\Modules\Portals\Direction\Widgets;

use App\Modules\Portals\Shared\Widgets\BaseWidget;
use Core\Database;

class KpiScolariteWidget extends BaseWidget
{
    public function getId(): string    { return 'direction_kpi_scolarite'; }
    public function getTitle(): string { return 'KPI Scolarité'; }
    public function getIcon(): string  { return 'graduation-cap'; }
    public function getPortals(): array { return ['direction']; }
    public function getPermissions(): array { return ['eleves.view']; }
    public function getDefaultSize(): string { return 'sm'; }
    public function getDefaultOrder(): int   { return 1; }
    public function isRefreshable(): bool    { return true; }
    public function getRefreshInterval(): int { return 300; }
    public function getTemplate(): string { return 'Portals::Shared/Widgets/direction/kpi_scolarite'; }

    public function getData(int $etab, int $userId, array $config): array
    {
        try {
            $pdo = Database::getInstance()->getConnection();

            $eleves = $pdo->prepare('SELECT COUNT(*) FROM eleves WHERE etablissement_id=? AND deleted_at IS NULL');
            $eleves->execute([$etab]);

            $classes = $pdo->prepare('SELECT COUNT(*) FROM classes WHERE etablissement_id=? AND deleted_at IS NULL');
            $classes->execute([$etab]);

            $inscrits = $pdo->prepare('SELECT COUNT(*) FROM inscriptions WHERE etablissement_id=? AND statut="active" AND deleted_at IS NULL');
            $inscrits->execute([$etab]);

            return [
                'nb_eleves'   => (int)$eleves->fetchColumn(),
                'nb_classes'  => (int)$classes->fetchColumn(),
                'nb_inscrits' => (int)$inscrits->fetchColumn(),
            ];
        } catch (\Throwable) {
            return ['nb_eleves' => 0, 'nb_classes' => 0, 'nb_inscrits' => 0];
        }
    }
}

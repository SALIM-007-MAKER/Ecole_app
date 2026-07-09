<?php
declare(strict_types=1);

namespace App\Modules\Portals\Direction\Widgets;

use App\Modules\Portals\Shared\Widgets\BaseWidget;
use Core\Database;

class KpiRhWidget extends BaseWidget
{
    public function getId(): string    { return 'direction_kpi_rh'; }
    public function getTitle(): string { return 'KPI RH'; }
    public function getIcon(): string  { return 'users'; }
    public function getPortals(): array { return ['direction']; }
    public function getPermissions(): array { return ['employee.view']; }
    public function getDefaultSize(): string { return 'sm'; }
    public function getDefaultOrder(): int   { return 4; }
    public function isRefreshable(): bool    { return true; }
    public function getRefreshInterval(): int { return 300; }
    public function getTemplate(): string { return 'Portals::Shared/Widgets/direction/kpi_rh'; }

    public function getData(int $etab, int $userId, array $config): array
    {
        try {
            $pdo   = Database::getInstance()->getConnection();
            $today = date('Y-m-d');

            $effectif = $pdo->prepare('SELECT COUNT(*) FROM rh_employes WHERE etablissement_id=? AND statut="actif" AND deleted_at IS NULL');
            $effectif->execute([$etab]);

            $presents = $pdo->prepare('SELECT COUNT(*) FROM rh_presences WHERE etablissement_id=? AND date_presence=? AND statut="present" AND deleted_at IS NULL');
            $presents->execute([$etab, $today]);

            $conges = $pdo->prepare('SELECT COUNT(*) FROM rh_conges WHERE etablissement_id=? AND statut="approuve" AND date_debut<=? AND date_fin>=? AND deleted_at IS NULL');
            $conges->execute([$etab, $today, $today]);

            $nbEff = (int)$effectif->fetchColumn();
            $nbPre = (int)$presents->fetchColumn();

            return [
                'effectif'       => $nbEff,
                'nb_presents'    => $nbPre,
                'taux_presence'  => $nbEff > 0 ? round($nbPre / $nbEff * 100, 1) : 0,
                'nb_conges'      => (int)$conges->fetchColumn(),
            ];
        } catch (\Throwable) {
            return ['effectif' => 0, 'nb_presents' => 0, 'taux_presence' => 0, 'nb_conges' => 0];
        }
    }
}

<?php
declare(strict_types=1);

namespace App\Modules\Portals\Direction\Widgets;

use App\Modules\Portals\Shared\Widgets\BaseWidget;
use Core\Database;

class KpiFinanceWidget extends BaseWidget
{
    public function getId(): string    { return 'direction_kpi_finance'; }
    public function getTitle(): string { return 'KPI Finance'; }
    public function getIcon(): string  { return 'trending-up'; }
    public function getPortals(): array { return ['direction']; }
    public function getPermissions(): array { return ['finance.dashboard.view']; }
    public function getDefaultSize(): string { return 'sm'; }
    public function getDefaultOrder(): int   { return 3; }
    public function isRefreshable(): bool    { return true; }
    public function getRefreshInterval(): int { return 300; }
    public function getTemplate(): string { return 'Portals::Shared/Widgets/direction/kpi_finance'; }

    public function getData(int $etab, int $userId, array $config): array
    {
        try {
            $pdo   = Database::getInstance()->getConnection();
            $month = date('Y-m');

            $recettes = $pdo->prepare(
                'SELECT COALESCE(SUM(montant),0) FROM finance_paiements
                 WHERE etablissement_id=? AND DATE_FORMAT(date_paiement,"%Y-%m")=? AND deleted_at IS NULL'
            );
            $recettes->execute([$etab, $month]);

            $impayes = $pdo->prepare(
                'SELECT COALESCE(SUM(montant_total - COALESCE(montant_paye,0)),0) FROM finance_factures
                 WHERE etablissement_id=? AND statut IN ("emise","partielle") AND deleted_at IS NULL'
            );
            $impayes->execute([$etab]);

            return [
                'recettes_mois'   => round((float)$recettes->fetchColumn(), 2),
                'impayes_total'   => round((float)$impayes->fetchColumn(), 2),
                'mois'            => $month,
            ];
        } catch (\Throwable) {
            return ['recettes_mois' => 0, 'impayes_total' => 0, 'mois' => date('Y-m')];
        }
    }
}

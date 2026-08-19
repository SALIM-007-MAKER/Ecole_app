<?php
declare(strict_types=1);

namespace App\Modules\Portals\Comptabilite\Widgets;

use App\Modules\Portals\Shared\Widgets\BaseWidget;
use Core\Database;

class RecettesMoisWidget extends BaseWidget
{
    public function getId(): string    { return 'compta_recettes_mois'; }
    public function getTitle(): string { return 'Recettes du mois'; }
    public function getIcon(): string  { return 'trending-up'; }
    public function getPortals(): array { return ['comptabilite']; }
    public function getPermissions(): array { return ['finance.report.view']; }
    public function getDefaultSize(): string { return 'sm'; }
    public function getDefaultOrder(): int   { return 3; }
    public function isRefreshable(): bool    { return true; }
    public function getRefreshInterval(): int { return 300; }
    public function getTemplate(): string { return 'Portals::Shared/Widgets/comptabilite/recettes_mois'; }

    public function getData(int $etab, int $userId, array $config): array
    {
        try {
            $pdo   = Database::getInstance()->getConnection();
            $month = date('Y-m');
            $prev  = date('Y-m', strtotime('first day of last month'));

            $cur = $pdo->prepare('SELECT COALESCE(SUM(montant_applique),0) FROM finance_paiements WHERE statut="complete" AND DATE_FORMAT(date_paiement,"%Y-%m")=?');
            $cur->execute([$month]);
            $prevStmt = $pdo->prepare('SELECT COALESCE(SUM(montant_applique),0) FROM finance_paiements WHERE statut="complete" AND DATE_FORMAT(date_paiement,"%Y-%m")=?');
            $prevStmt->execute([$prev]);

            $total    = (float)$cur->fetchColumn();
            $prevTotal = (float)$prevStmt->fetchColumn();
            $variation = $prevTotal > 0 ? round(($total - $prevTotal) / $prevTotal * 100, 1) : 0;

            return ['total' => $total, 'mois_precedent' => $prevTotal, 'variation_pct' => $variation, 'mois' => $month];
        } catch (\Throwable) {
            return ['total' => 0, 'mois_precedent' => 0, 'variation_pct' => 0, 'mois' => date('Y-m')];
        }
    }
}

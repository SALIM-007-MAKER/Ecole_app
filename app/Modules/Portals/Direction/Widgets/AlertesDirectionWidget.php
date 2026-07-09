<?php
declare(strict_types=1);

namespace App\Modules\Portals\Direction\Widgets;

use App\Modules\Portals\Shared\Widgets\BaseWidget;
use Core\Database;

class AlertesDirectionWidget extends BaseWidget
{
    public function getId(): string    { return 'direction_alertes'; }
    public function getTitle(): string { return 'Alertes'; }
    public function getIcon(): string  { return 'bell'; }
    public function getPortals(): array { return ['direction']; }
    public function getPermissions(): array { return ['eleves.view']; }
    public function getDefaultSize(): string { return 'sm'; }
    public function getDefaultOrder(): int   { return 8; }
    public function isRefreshable(): bool    { return true; }
    public function getRefreshInterval(): int { return 120; }
    public function getTemplate(): string { return 'Portals::Shared/Widgets/direction/alertes'; }

    public function getData(int $etab, int $userId, array $config): array
    {
        $alertes = [];
        try {
            $pdo   = Database::getInstance()->getConnection();
            $today = date('Y-m-d');

            $impayes = $pdo->prepare('SELECT COUNT(*) FROM finance_factures WHERE etablissement_id=? AND statut="emise" AND date_echeance < ? AND deleted_at IS NULL');
            $impayes->execute([$etab, $today]);
            $nbImp = (int)$impayes->fetchColumn();
            if ($nbImp > 0) $alertes[] = ['type' => 'warning', 'message' => "$nbImp facture(s) en retard de paiement", 'url' => '/v2/portals/direction/finance'];

            $contrats = $pdo->prepare('SELECT COUNT(*) FROM rh_contrats WHERE etablissement_id=? AND statut="actif" AND date_fin BETWEEN ? AND DATE_ADD(?,INTERVAL 30 DAY) AND deleted_at IS NULL');
            $contrats->execute([$etab, $today, $today]);
            $nbCont = (int)$contrats->fetchColumn();
            if ($nbCont > 0) $alertes[] = ['type' => 'info', 'message' => "$nbCont contrat(s) expirent dans 30 jours", 'url' => '/v2/portals/direction/rh'];
        } catch (\Throwable) {}

        return ['alertes' => $alertes, 'count' => count($alertes)];
    }
}

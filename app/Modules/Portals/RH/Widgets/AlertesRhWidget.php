<?php
declare(strict_types=1);

namespace App\Modules\Portals\RH\Widgets;

use App\Modules\Portals\Shared\Widgets\BaseWidget;
use Core\Database;

class AlertesRhWidget extends BaseWidget
{
    public function getId(): string    { return 'rh_alertes_rh'; }
    public function getTitle(): string { return 'Alertes RH'; }
    public function getIcon(): string  { return 'bell'; }
    public function getPortals(): array { return ['rh']; }
    public function getPermissions(): array { return ['employee.view']; }
    public function getDefaultSize(): string { return 'sm'; }
    public function getDefaultOrder(): int   { return 7; }
    public function isRefreshable(): bool    { return true; }
    public function getRefreshInterval(): int { return 120; }
    public function getTemplate(): string { return 'Portals::Shared/Widgets/rh/alertes_rh'; }

    public function getData(int $etab, int $userId, array $config): array
    {
        $alertes = [];
        try {
            $pdo   = Database::getInstance()->getConnection();
            $today = date('Y-m-d');

            $conges = $pdo->prepare('SELECT COUNT(*) FROM rh_conges WHERE etablissement_id=? AND statut="en_attente" AND deleted_at IS NULL');
            $conges->execute([$etab]);
            $nb = (int)$conges->fetchColumn();
            if ($nb > 0) $alertes[] = ['type' => 'warning', 'message' => "$nb demande(s) de congé en attente"];

            $contrats = $pdo->prepare('SELECT COUNT(*) FROM rh_contrats WHERE etablissement_id=? AND statut="actif" AND date_fin <= DATE_ADD(?,INTERVAL 30 DAY) AND date_fin >= ? AND deleted_at IS NULL');
            $contrats->execute([$etab, $today, $today]);
            $nbC = (int)$contrats->fetchColumn();
            if ($nbC > 0) $alertes[] = ['type' => 'info', 'message' => "$nbC contrat(s) expirent dans 30 jours"];
        } catch (\Throwable) {}

        return ['alertes' => $alertes, 'count' => count($alertes)];
    }
}

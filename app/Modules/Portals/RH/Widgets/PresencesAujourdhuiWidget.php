<?php
declare(strict_types=1);

namespace App\Modules\Portals\RH\Widgets;

use App\Modules\Portals\Shared\Widgets\BaseWidget;
use Core\Database;

class PresencesAujourdhuiWidget extends BaseWidget
{
    public function getId(): string    { return 'rh_presences_aujourd_hui'; }
    public function getTitle(): string { return 'Présences aujourd\'hui'; }
    public function getIcon(): string  { return 'check-circle'; }
    public function getPortals(): array { return ['rh']; }
    public function getPermissions(): array { return ['rh.presence.view']; }
    public function getDefaultSize(): string { return 'sm'; }
    public function getDefaultOrder(): int   { return 1; }
    public function isRefreshable(): bool    { return true; }
    public function getRefreshInterval(): int { return 120; }
    public function getTemplate(): string { return 'Portals::Shared/Widgets/rh/presences_aujourd_hui'; }

    public function getData(int $etab, int $userId, array $config): array
    {
        try {
            $pdo   = Database::getInstance()->getConnection();
            $today = date('Y-m-d');
            $stmt  = $pdo->prepare(
                'SELECT statut, COUNT(*) AS nb FROM rh_presences
                 WHERE etablissement_id=? AND date_presence=? AND deleted_at IS NULL
                 GROUP BY statut'
            );
            $stmt->execute([$etab, $today]);
            $rows = $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);
            $eff  = $pdo->prepare('SELECT COUNT(*) FROM rh_employes WHERE etablissement_id=? AND statut="actif" AND deleted_at IS NULL');
            $eff->execute([$etab]);
            $nbEff = (int)$eff->fetchColumn();
            $nbPre = (int)($rows['present'] ?? 0);

            return [
                'par_statut'    => $rows,
                'nb_employes'   => $nbEff,
                'nb_presents'   => $nbPre,
                'taux'          => $nbEff > 0 ? round($nbPre / $nbEff * 100, 1) : 0,
                'date'          => $today,
            ];
        } catch (\Throwable) {
            return ['par_statut' => [], 'nb_employes' => 0, 'nb_presents' => 0, 'taux' => 0, 'date' => date('Y-m-d')];
        }
    }
}

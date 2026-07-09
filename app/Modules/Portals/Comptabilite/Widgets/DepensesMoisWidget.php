<?php
declare(strict_types=1);

namespace App\Modules\Portals\Comptabilite\Widgets;

use App\Modules\Portals\Shared\Widgets\BaseWidget;
use Core\Database;

class DepensesMoisWidget extends BaseWidget
{
    public function getId(): string    { return 'compta_depenses_mois'; }
    public function getTitle(): string { return 'Dépenses du mois'; }
    public function getIcon(): string  { return 'trending-down'; }
    public function getPortals(): array { return ['comptabilite']; }
    public function getPermissions(): array { return ['finance.report.view']; }
    public function getDefaultSize(): string { return 'sm'; }
    public function getDefaultOrder(): int   { return 8; }
    public function isRefreshable(): bool    { return true; }
    public function getRefreshInterval(): int { return 300; }
    public function getTemplate(): string { return 'Portals::Shared/Widgets/comptabilite/depenses_mois'; }

    public function getData(int $etab, int $userId, array $config): array
    {
        try {
            $pdo   = Database::getInstance()->getConnection();
            $month = date('Y-m');
            $stmt  = $pdo->prepare(
                'SELECT COALESCE(SUM(montant),0) AS total, COUNT(*) AS nb
                 FROM finance_caisse_mouvements
                 WHERE etablissement_id=? AND type_mouvement="sortie"
                   AND DATE_FORMAT(created_at,"%Y-%m")=? AND deleted_at IS NULL'
            );
            $stmt->execute([$etab, $month]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            return ['total' => (float)($row['total'] ?? 0), 'nb' => (int)($row['nb'] ?? 0), 'mois' => $month];
        } catch (\Throwable) {
            return ['total' => 0, 'nb' => 0, 'mois' => date('Y-m')];
        }
    }
}

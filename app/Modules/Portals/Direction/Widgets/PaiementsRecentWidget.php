<?php
declare(strict_types=1);

namespace App\Modules\Portals\Direction\Widgets;

use App\Modules\Portals\Shared\Widgets\BaseWidget;
use Core\Database;

class PaiementsRecentWidget extends BaseWidget
{
    public function getId(): string    { return 'direction_paiements_recent'; }
    public function getTitle(): string { return 'Derniers paiements'; }
    public function getIcon(): string  { return 'credit-card'; }
    public function getPortals(): array { return ['direction']; }
    public function getPermissions(): array { return ['finance.dashboard.view']; }
    public function getDefaultSize(): string { return 'md'; }
    public function getDefaultOrder(): int   { return 6; }
    public function isRefreshable(): bool    { return true; }
    public function getRefreshInterval(): int { return 120; }
    public function getTemplate(): string { return 'Portals::Shared/Widgets/direction/paiements_recent'; }

    public function getData(int $etab, int $userId, array $config): array
    {
        try {
            $pdo  = Database::getInstance()->getConnection();
            $stmt = $pdo->prepare(
                'SELECT p.montant, p.date_paiement, p.mode_paiement,
                        CONCAT(u.prenom," ",u.nom) AS eleve_nom
                 FROM finance_paiements p
                 JOIN finance_factures f ON f.id = p.facture_id
                 LEFT JOIN eleves e ON e.id = f.eleve_id
                 LEFT JOIN users u ON u.id = e.user_id
                 WHERE p.etablissement_id=? AND p.deleted_at IS NULL
                 ORDER BY p.date_paiement DESC LIMIT 8'
            );
            $stmt->execute([$etab]);
            return ['paiements' => $stmt->fetchAll(\PDO::FETCH_ASSOC)];
        } catch (\Throwable) {
            return ['paiements' => []];
        }
    }
}

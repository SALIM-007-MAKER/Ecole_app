<?php
declare(strict_types=1);

namespace App\Modules\Portals\Comptabilite\Widgets;

use App\Modules\Portals\Shared\Widgets\BaseWidget;
use Core\Database;

class DerniersPaiementsWidget extends BaseWidget
{
    public function getId(): string    { return 'compta_derniers_paiements'; }
    public function getTitle(): string { return 'Derniers paiements'; }
    public function getIcon(): string  { return 'credit-card'; }
    public function getPortals(): array { return ['comptabilite']; }
    public function getPermissions(): array { return ['finance.payment.view']; }
    public function getDefaultSize(): string { return 'md'; }
    public function getDefaultOrder(): int   { return 6; }
    public function isRefreshable(): bool    { return true; }
    public function getRefreshInterval(): int { return 120; }
    public function getTemplate(): string { return 'Portals::Shared/Widgets/comptabilite/derniers_paiements'; }

    public function getData(int $etab, int $userId, array $config): array
    {
        try {
            $pdo  = Database::getInstance()->getConnection();
            $stmt = $pdo->prepare(
                'SELECT p.montant_applique AS montant, p.date_paiement, mp.nom AS mode_paiement,
                        CONCAT(e.prenom," ",e.nom) AS eleve_nom
                 FROM finance_paiements p
                 JOIN finance_factures f ON f.id = p.facture_id
                 LEFT JOIN eleves e ON e.id = f.eleve_id
                 LEFT JOIN finance_modes_paiement mp ON mp.id = p.mode_paiement_id
                 WHERE p.statut = "complete"
                 ORDER BY p.date_paiement DESC LIMIT 10'
            );
            $stmt->execute();
            return ['paiements' => $stmt->fetchAll(\PDO::FETCH_ASSOC)];
        } catch (\Throwable) {
            return ['paiements' => []];
        }
    }
}

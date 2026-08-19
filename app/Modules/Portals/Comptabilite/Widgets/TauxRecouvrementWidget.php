<?php
declare(strict_types=1);

namespace App\Modules\Portals\Comptabilite\Widgets;

use App\Modules\Portals\Shared\Widgets\BaseWidget;
use Core\Database;

class TauxRecouvrementWidget extends BaseWidget
{
    public function getId(): string    { return 'compta_taux_recouvrement'; }
    public function getTitle(): string { return 'Taux de recouvrement'; }
    public function getIcon(): string  { return 'percent'; }
    public function getPortals(): array { return ['comptabilite']; }
    public function getPermissions(): array { return ['finance.report.view']; }
    public function getDefaultSize(): string { return 'sm'; }
    public function getDefaultOrder(): int   { return 5; }
    public function getTemplate(): string { return 'Portals::Shared/Widgets/comptabilite/taux_recouvrement'; }

    public function getData(int $etab, int $userId, array $config): array
    {
        try {
            $pdo  = Database::getInstance()->getConnection();
            $stmt = $pdo->prepare(
                'SELECT SUM(montant_total) AS total_facture,
                        SUM(COALESCE(montant_paye,0)) AS total_encaisse
                 FROM finance_factures
                 WHERE statut != "brouillon"'
            );
            $stmt->execute();
            $row     = $stmt->fetch(\PDO::FETCH_ASSOC);
            $facture = (float)($row['total_facture'] ?? 0);
            $encaisse = (float)($row['total_encaisse'] ?? 0);
            $taux    = $facture > 0 ? round($encaisse / $facture * 100, 1) : 0;

            return ['total_facture' => $facture, 'total_encaisse' => $encaisse, 'taux' => $taux];
        } catch (\Throwable) {
            return ['total_facture' => 0, 'total_encaisse' => 0, 'taux' => 0];
        }
    }
}

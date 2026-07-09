<?php
declare(strict_types=1);

namespace App\Modules\Portals\Comptabilite\Widgets;

use App\Modules\Portals\Shared\Widgets\BaseWidget;
use Core\Database;

class CaisseDuJourWidget extends BaseWidget
{
    public function getId(): string    { return 'compta_caisse_du_jour'; }
    public function getTitle(): string { return 'Caisse du jour'; }
    public function getIcon(): string  { return 'dollar-sign'; }
    public function getPortals(): array { return ['comptabilite']; }
    public function getPermissions(): array { return ['finance.caisse.view']; }
    public function getDefaultSize(): string { return 'sm'; }
    public function getDefaultOrder(): int   { return 1; }
    public function isRefreshable(): bool    { return true; }
    public function getRefreshInterval(): int { return 120; }
    public function getTemplate(): string { return 'Portals::Shared/Widgets/comptabilite/caisse_du_jour'; }

    public function getData(int $etab, int $userId, array $config): array
    {
        try {
            $pdo   = Database::getInstance()->getConnection();
            $today = date('Y-m-d');

            $encaisse = $pdo->prepare('SELECT COALESCE(SUM(montant),0) FROM finance_paiements WHERE etablissement_id=? AND DATE(date_paiement)=? AND deleted_at IS NULL');
            $encaisse->execute([$etab, $today]);

            $mouvements = $pdo->prepare('SELECT type_mouvement, SUM(montant) AS total FROM finance_caisse_mouvements WHERE etablissement_id=? AND DATE(created_at)=? AND deleted_at IS NULL GROUP BY type_mouvement');
            $mouvements->execute([$etab, $today]);
            $mvts = $mouvements->fetchAll(\PDO::FETCH_KEY_PAIR);

            return [
                'encaisse'   => (float)$encaisse->fetchColumn(),
                'entrees'    => (float)($mvts['entree'] ?? 0),
                'sorties'    => (float)($mvts['sortie'] ?? 0),
                'date'       => $today,
            ];
        } catch (\Throwable) {
            return ['encaisse' => 0, 'entrees' => 0, 'sorties' => 0, 'date' => date('Y-m-d')];
        }
    }
}

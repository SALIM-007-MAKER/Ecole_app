<?php
declare(strict_types=1);

namespace App\Modules\Portals\Comptabilite\Widgets;

use App\Modules\Portals\Shared\Widgets\BaseWidget;
use Core\Database;

class FacturesEnAttenteWidget extends BaseWidget
{
    public function getId(): string    { return 'compta_factures_en_attente'; }
    public function getTitle(): string { return 'Factures en attente'; }
    public function getIcon(): string  { return 'file-minus'; }
    public function getPortals(): array { return ['comptabilite']; }
    public function getPermissions(): array { return ['finance.invoice.view']; }
    public function getDefaultSize(): string { return 'sm'; }
    public function getDefaultOrder(): int   { return 2; }
    public function isRefreshable(): bool    { return true; }
    public function getRefreshInterval(): int { return 300; }
    public function getTemplate(): string { return 'Portals::Shared/Widgets/comptabilite/factures_en_attente'; }

    public function getData(int $etab, int $userId, array $config): array
    {
        try {
            $pdo  = Database::getInstance()->getConnection();
            $stmt = $pdo->prepare(
                'SELECT statut, COUNT(*) AS nb, SUM(montant_total - COALESCE(montant_paye,0)) AS montant
                 FROM finance_factures
                 WHERE etablissement_id=? AND statut IN ("emise","partielle") AND deleted_at IS NULL
                 GROUP BY statut'
            );
            $stmt->execute([$etab]);
            $rows  = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            $total = ['nb' => 0, 'montant' => 0];
            foreach ($rows as $r) {
                $total['nb']      += (int)$r['nb'];
                $total['montant'] += (float)$r['montant'];
            }
            return ['par_statut' => $rows, 'total' => $total];
        } catch (\Throwable) {
            return ['par_statut' => [], 'total' => ['nb' => 0, 'montant' => 0]];
        }
    }
}

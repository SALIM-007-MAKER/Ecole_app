<?php
declare(strict_types=1);

namespace App\Modules\Portals\Comptabilite\Widgets;

use App\Modules\Portals\Shared\Widgets\BaseWidget;
use Core\Database;

class ImpayesWidget extends BaseWidget
{
    public function getId(): string    { return 'compta_impayes'; }
    public function getTitle(): string { return 'Impayés'; }
    public function getIcon(): string  { return 'alert-triangle'; }
    public function getPortals(): array { return ['comptabilite']; }
    public function getPermissions(): array { return ['finance.invoice.view']; }
    public function getDefaultSize(): string { return 'sm'; }
    public function getDefaultOrder(): int   { return 4; }
    public function isRefreshable(): bool    { return true; }
    public function getRefreshInterval(): int { return 300; }
    public function getTemplate(): string { return 'Portals::Shared/Widgets/comptabilite/impayes'; }

    public function getData(int $etab, int $userId, array $config): array
    {
        try {
            $pdo  = Database::getInstance()->getConnection();
            $stmt = $pdo->prepare(
                'SELECT COUNT(*) AS nb,
                        SUM(montant_total - COALESCE(montant_paye,0)) AS montant_total,
                        SUM(CASE WHEN date_echeance < CURDATE() THEN 1 ELSE 0 END) AS nb_en_retard
                 FROM finance_factures
                 WHERE etablissement_id=? AND statut IN ("emise","partielle") AND deleted_at IS NULL'
            );
            $stmt->execute([$etab]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            return [
                'nb'           => (int)($row['nb'] ?? 0),
                'montant_total' => (float)($row['montant_total'] ?? 0),
                'nb_en_retard' => (int)($row['nb_en_retard'] ?? 0),
            ];
        } catch (\Throwable) {
            return ['nb' => 0, 'montant_total' => 0, 'nb_en_retard' => 0];
        }
    }
}

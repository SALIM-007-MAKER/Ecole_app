<?php
declare(strict_types=1);

namespace App\Modules\Portals\Comptabilite\Widgets;

use App\Modules\Portals\Shared\Widgets\BaseWidget;
use Core\Database;

class ComparatifMensuelWidget extends BaseWidget
{
    public function getId(): string    { return 'compta_comparatif_mensuel'; }
    public function getTitle(): string { return 'Comparatif mensuel'; }
    public function getIcon(): string  { return 'bar-chart-2'; }
    public function getPortals(): array { return ['comptabilite']; }
    public function getPermissions(): array { return ['finance.report.view']; }
    public function getDefaultSize(): string { return 'lg'; }
    public function getDefaultOrder(): int   { return 7; }
    public function getTemplate(): string { return 'Portals::Shared/Widgets/comptabilite/comparatif_mensuel'; }

    public function getData(int $etab, int $userId, array $config): array
    {
        try {
            $pdo  = Database::getInstance()->getConnection();
            $stmt = $pdo->prepare(
                'SELECT DATE_FORMAT(date_paiement,"%Y-%m") AS mois,
                        SUM(montant) AS total, COUNT(*) AS nb
                 FROM finance_paiements
                 WHERE etablissement_id=? AND deleted_at IS NULL
                   AND date_paiement >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
                 GROUP BY DATE_FORMAT(date_paiement,"%Y-%m")
                 ORDER BY mois ASC'
            );
            $stmt->execute([$etab]);
            return ['mois' => $stmt->fetchAll(\PDO::FETCH_ASSOC)];
        } catch (\Throwable) {
            return ['mois' => []];
        }
    }
}

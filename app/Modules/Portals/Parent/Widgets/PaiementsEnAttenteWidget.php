<?php
declare(strict_types=1);

namespace App\Modules\Portals\Parent\Widgets;

use App\Modules\Portals\Shared\Widgets\BaseWidget;
use Core\Database;

class PaiementsEnAttenteWidget extends BaseWidget
{
    public function getId(): string    { return 'parent_paiements_en_attente'; }
    public function getTitle(): string { return 'Paiements en attente'; }
    public function getIcon(): string  { return 'alert-circle'; }
    public function getPortals(): array { return ['parent']; }
    public function getPermissions(): array { return ['finance.invoice.view']; }
    public function getDefaultSize(): string { return 'sm'; }
    public function getDefaultOrder(): int   { return 3; }
    public function isRefreshable(): bool    { return true; }
    public function getRefreshInterval(): int { return 300; }
    public function getTemplate(): string { return 'Portals::Shared/Widgets/parent/paiements_en_attente'; }

    public function getData(int $etab, int $userId, array $config): array
    {
        try {
            $pdo  = Database::getInstance()->getConnection();
            $stmt = $pdo->prepare(
                'SELECT f.id, f.numero, f.date_echeance,
                        (f.montant_total - COALESCE(f.montant_paye,0)) AS reste,
                        CONCAT(u.prenom," ",u.nom) AS enfant_nom
                 FROM finance_factures f
                 JOIN eleves e ON e.id = f.eleve_id
                 LEFT JOIN users u ON u.id = e.user_id
                 JOIN famille_eleve fe ON fe.eleve_id = e.id
                 JOIN famille_membres fm ON fm.famille_id = fe.famille_id
                 WHERE fm.user_id=? AND f.etablissement_id=?
                   AND f.statut IN ("emise","partielle") AND f.deleted_at IS NULL
                 ORDER BY f.date_echeance ASC LIMIT 5'
            );
            $stmt->execute([$userId, $etab]);
            $factures = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            $total    = array_sum(array_column($factures, 'reste'));
            return ['factures' => $factures, 'total_du' => $total, 'nb' => count($factures)];
        } catch (\Throwable) {
            return ['factures' => [], 'total_du' => 0, 'nb' => 0];
        }
    }
}

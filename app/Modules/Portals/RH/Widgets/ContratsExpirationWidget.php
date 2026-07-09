<?php
declare(strict_types=1);

namespace App\Modules\Portals\RH\Widgets;

use App\Modules\Portals\Shared\Widgets\BaseWidget;
use Core\Database;

class ContratsExpirationWidget extends BaseWidget
{
    public function getId(): string    { return 'rh_contrats_expiration'; }
    public function getTitle(): string { return 'Contrats à renouveler'; }
    public function getIcon(): string  { return 'alert-circle'; }
    public function getPortals(): array { return ['rh']; }
    public function getPermissions(): array { return ['contract.view']; }
    public function getDefaultSize(): string { return 'sm'; }
    public function getDefaultOrder(): int   { return 3; }
    public function isRefreshable(): bool    { return true; }
    public function getRefreshInterval(): int { return 600; }
    public function getTemplate(): string { return 'Portals::Shared/Widgets/rh/contrats_expiration'; }

    public function getData(int $etab, int $userId, array $config): array
    {
        try {
            $pdo   = Database::getInstance()->getConnection();
            $today = date('Y-m-d');
            $stmt  = $pdo->prepare(
                'SELECT c.id, c.date_fin, c.type_contrat,
                        DATEDIFF(c.date_fin, CURDATE()) AS jours_restants,
                        CONCAT(u.prenom," ",u.nom) AS employe_nom
                 FROM rh_contrats c
                 JOIN rh_employes emp ON emp.id = c.employe_id
                 LEFT JOIN users u ON u.id = emp.user_id
                 WHERE c.etablissement_id=? AND c.statut="actif" AND c.date_fin IS NOT NULL
                   AND c.date_fin <= DATE_ADD(?,INTERVAL 60 DAY) AND c.deleted_at IS NULL
                 ORDER BY c.date_fin ASC LIMIT 10'
            );
            $stmt->execute([$etab, $today]);
            $contrats = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            $urgent   = count(array_filter($contrats, fn($c) => $c['jours_restants'] <= 30));
            return ['contrats' => $contrats, 'total' => count($contrats), 'urgent' => $urgent];
        } catch (\Throwable) {
            return ['contrats' => [], 'total' => 0, 'urgent' => 0];
        }
    }
}

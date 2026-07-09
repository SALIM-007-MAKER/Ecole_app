<?php
declare(strict_types=1);

namespace App\Modules\Portals\RH\Widgets;

use App\Modules\Portals\Shared\Widgets\BaseWidget;
use Core\Database;

class CongesEnAttenteWidget extends BaseWidget
{
    public function getId(): string    { return 'rh_conges_en_attente'; }
    public function getTitle(): string { return 'Congés en attente'; }
    public function getIcon(): string  { return 'umbrella'; }
    public function getPortals(): array { return ['rh']; }
    public function getPermissions(): array { return ['leave.manage']; }
    public function getDefaultSize(): string { return 'sm'; }
    public function getDefaultOrder(): int   { return 2; }
    public function isRefreshable(): bool    { return true; }
    public function getRefreshInterval(): int { return 120; }
    public function getTemplate(): string { return 'Portals::Shared/Widgets/rh/conges_en_attente'; }

    public function getData(int $etab, int $userId, array $config): array
    {
        try {
            $pdo  = Database::getInstance()->getConnection();
            $stmt = $pdo->prepare(
                'SELECT c.id, c.date_debut, c.date_fin, c.type_conge,
                        CONCAT(u.prenom," ",u.nom) AS employe_nom
                 FROM rh_conges c
                 JOIN rh_employes emp ON emp.id = c.employe_id
                 LEFT JOIN users u ON u.id = emp.user_id
                 WHERE c.etablissement_id=? AND c.statut="en_attente" AND c.deleted_at IS NULL
                 ORDER BY c.created_at ASC LIMIT 10'
            );
            $stmt->execute([$etab]);
            $conges = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            return ['conges' => $conges, 'total' => count($conges)];
        } catch (\Throwable) {
            return ['conges' => [], 'total' => 0];
        }
    }
}

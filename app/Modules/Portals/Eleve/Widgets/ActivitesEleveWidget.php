<?php
declare(strict_types=1);

namespace App\Modules\Portals\Eleve\Widgets;

use App\Modules\Portals\Shared\Widgets\BaseWidget;
use Core\Database;

class ActivitesEleveWidget extends BaseWidget
{
    public function getId(): string    { return 'eleve_activites'; }
    public function getTitle(): string { return 'Mes activités'; }
    public function getIcon(): string  { return 'zap'; }
    public function getPortals(): array { return ['eleve']; }
    public function getPermissions(): array { return ['activity.view']; }
    public function getDefaultSize(): string { return 'sm'; }
    public function getDefaultOrder(): int   { return 6; }
    public function getTemplate(): string { return 'Portals::Shared/Widgets/eleve/activites'; }

    public function getData(int $etab, int $userId, array $config): array
    {
        try {
            $pdo  = Database::getInstance()->getConnection();
            $stmt = $pdo->prepare(
                'SELECT a.titre, a.type_activite, a.date_debut, ia.statut_inscription
                 FROM vs_activites a
                 JOIN vs_activite_inscriptions ia ON ia.activite_id = a.id
                 WHERE ia.eleve_user_id=? AND a.etablissement_id=?
                   AND a.deleted_at IS NULL AND ia.deleted_at IS NULL
                 ORDER BY a.date_debut ASC LIMIT 5'
            );
            $stmt->execute([$userId, $etab]);
            return ['activites' => $stmt->fetchAll(\PDO::FETCH_ASSOC)];
        } catch (\Throwable) {
            return ['activites' => []];
        }
    }
}

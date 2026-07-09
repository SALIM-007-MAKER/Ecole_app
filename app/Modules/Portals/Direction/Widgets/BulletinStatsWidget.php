<?php
declare(strict_types=1);

namespace App\Modules\Portals\Direction\Widgets;

use App\Modules\Portals\Shared\Widgets\BaseWidget;
use Core\Database;

class BulletinStatsWidget extends BaseWidget
{
    public function getId(): string    { return 'direction_bulletin_stats'; }
    public function getTitle(): string { return 'Statistiques bulletins'; }
    public function getIcon(): string  { return 'file-text'; }
    public function getPortals(): array { return ['direction']; }
    public function getPermissions(): array { return ['academique.bulletin.view']; }
    public function getDefaultSize(): string { return 'md'; }
    public function getDefaultOrder(): int   { return 7; }
    public function getTemplate(): string { return 'Portals::Shared/Widgets/direction/bulletin_stats'; }

    public function getData(int $etab, int $userId, array $config): array
    {
        try {
            $pdo  = Database::getInstance()->getConnection();
            $stmt = $pdo->prepare(
                'SELECT p.nom AS periode, COUNT(b.id) AS nb_bulletins,
                        AVG(b.moyenne_generale) AS moy_generale,
                        SUM(CASE WHEN b.mention IN ("TB","B") THEN 1 ELSE 0 END) AS nb_mentions
                 FROM bulletins_v2 b
                 JOIN periodes_scolaires p ON p.id = b.periode_id
                 WHERE b.etablissement_id=? AND b.deleted_at IS NULL
                 GROUP BY b.periode_id ORDER BY p.date_debut DESC LIMIT 4'
            );
            $stmt->execute([$etab]);
            return ['periodes' => $stmt->fetchAll(\PDO::FETCH_ASSOC)];
        } catch (\Throwable) {
            return ['periodes' => []];
        }
    }
}

<?php
declare(strict_types=1);

namespace App\Modules\Portals\Direction\Widgets;

use App\Modules\Portals\Shared\Widgets\BaseWidget;
use Core\Database;

class CalendrierWidget extends BaseWidget
{
    public function getId(): string    { return 'direction_calendrier'; }
    public function getTitle(): string { return 'Calendrier'; }
    public function getIcon(): string  { return 'calendar'; }
    public function getPortals(): array { return ['direction']; }
    public function getPermissions(): array { return ['eleves.view']; }
    public function getDefaultSize(): string { return 'md'; }
    public function getDefaultOrder(): int   { return 9; }
    public function getTemplate(): string { return 'Portals::Shared/Widgets/direction/calendrier'; }

    public function getData(int $etab, int $userId, array $config): array
    {
        try {
            $pdo   = Database::getInstance()->getConnection();
            $today = date('Y-m-d');
            $stmt  = $pdo->prepare(
                'SELECT titre, type_evenement, date_debut, date_fin FROM vs_activites
                 WHERE etablissement_id=? AND date_debut >= ? AND date_debut <= DATE_ADD(?,INTERVAL 14 DAY)
                   AND deleted_at IS NULL ORDER BY date_debut ASC LIMIT 10'
            );
            $stmt->execute([$etab, $today, $today]);
            $evenements = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            return ['evenements' => $evenements, 'today' => $today];
        } catch (\Throwable) {
            return ['evenements' => [], 'today' => date('Y-m-d')];
        }
    }
}

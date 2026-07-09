<?php
declare(strict_types=1);

namespace App\Modules\Portals\Direction\Widgets;

use App\Modules\Portals\Shared\Widgets\BaseWidget;
use Core\Database;

class AbsencesSemaineWidget extends BaseWidget
{
    public function getId(): string    { return 'direction_absences_semaine'; }
    public function getTitle(): string { return 'Absences cette semaine'; }
    public function getIcon(): string  { return 'user-x'; }
    public function getPortals(): array { return ['direction']; }
    public function getPermissions(): array { return ['attendance.view']; }
    public function getDefaultSize(): string { return 'md'; }
    public function getDefaultOrder(): int   { return 5; }
    public function isRefreshable(): bool    { return true; }
    public function getRefreshInterval(): int { return 300; }
    public function getTemplate(): string { return 'Portals::Shared/Widgets/direction/absences_semaine'; }

    public function getData(int $etab, int $userId, array $config): array
    {
        try {
            $pdo     = Database::getInstance()->getConnection();
            $lundi   = date('Y-m-d', strtotime('monday this week'));
            $dimanche = date('Y-m-d', strtotime('sunday this week'));

            $stmt = $pdo->prepare(
                'SELECT DATE(date_absence) AS jour, COUNT(*) AS nb
                 FROM vs_absences
                 WHERE etablissement_id=? AND date_absence BETWEEN ? AND ? AND deleted_at IS NULL
                 GROUP BY DATE(date_absence) ORDER BY jour'
            );
            $stmt->execute([$etab, $lundi, $dimanche]);
            $parJour = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            $total   = array_sum(array_column($parJour, 'nb'));

            return ['par_jour' => $parJour, 'total' => $total, 'semaine_debut' => $lundi];
        } catch (\Throwable) {
            return ['par_jour' => [], 'total' => 0, 'semaine_debut' => date('Y-m-d')];
        }
    }
}

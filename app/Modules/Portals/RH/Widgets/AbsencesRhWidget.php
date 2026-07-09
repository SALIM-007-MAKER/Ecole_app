<?php
declare(strict_types=1);

namespace App\Modules\Portals\RH\Widgets;

use App\Modules\Portals\Shared\Widgets\BaseWidget;
use Core\Database;

class AbsencesRhWidget extends BaseWidget
{
    public function getId(): string    { return 'rh_absences_rh'; }
    public function getTitle(): string { return 'Absences du personnel'; }
    public function getIcon(): string  { return 'user-x'; }
    public function getPortals(): array { return ['rh']; }
    public function getPermissions(): array { return ['rh.presence.view']; }
    public function getDefaultSize(): string { return 'sm'; }
    public function getDefaultOrder(): int   { return 8; }
    public function isRefreshable(): bool    { return true; }
    public function getRefreshInterval(): int { return 300; }
    public function getTemplate(): string { return 'Portals::Shared/Widgets/rh/absences_rh'; }

    public function getData(int $etab, int $userId, array $config): array
    {
        try {
            $pdo     = Database::getInstance()->getConnection();
            $lundi   = date('Y-m-d', strtotime('monday this week'));
            $stmt    = $pdo->prepare(
                'SELECT DATE(date_presence) AS jour, COUNT(*) AS nb
                 FROM rh_presences
                 WHERE etablissement_id=? AND statut IN ("absent","retard")
                   AND date_presence >= ? AND deleted_at IS NULL
                 GROUP BY DATE(date_presence) ORDER BY jour'
            );
            $stmt->execute([$etab, $lundi]);
            $parJour = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            return ['par_jour' => $parJour, 'total' => array_sum(array_column($parJour, 'nb'))];
        } catch (\Throwable) {
            return ['par_jour' => [], 'total' => 0];
        }
    }
}

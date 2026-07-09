<?php
declare(strict_types=1);

namespace App\Modules\Portals\Eleve\Widgets;

use App\Modules\Portals\Shared\Widgets\BaseWidget;
use Core\Database;

class AbsencesEleveWidget extends BaseWidget
{
    public function getId(): string    { return 'eleve_absences'; }
    public function getTitle(): string { return 'Mes absences'; }
    public function getIcon(): string  { return 'user-x'; }
    public function getPortals(): array { return ['eleve']; }
    public function getPermissions(): array { return ['absences.view_own']; }
    public function getDefaultSize(): string { return 'sm'; }
    public function getDefaultOrder(): int   { return 3; }
    public function getTemplate(): string { return 'Portals::Shared/Widgets/eleve/absences'; }

    public function getData(int $etab, int $userId, array $config): array
    {
        try {
            $pdo  = Database::getInstance()->getConnection();
            $stmt = $pdo->prepare(
                'SELECT COUNT(*) AS total,
                        SUM(CASE WHEN justifiee=1 THEN 1 ELSE 0 END) AS justifiees,
                        SUM(CASE WHEN justifiee=0 THEN 1 ELSE 0 END) AS non_justifiees
                 FROM vs_absences
                 WHERE eleve_user_id=? AND etablissement_id=? AND deleted_at IS NULL'
            );
            $stmt->execute([$userId, $etab]);
            $stats = $stmt->fetch(\PDO::FETCH_ASSOC);

            $recent = $pdo->prepare(
                'SELECT date_absence, motif, justifiee FROM vs_absences
                 WHERE eleve_user_id=? AND etablissement_id=? AND deleted_at IS NULL
                 ORDER BY date_absence DESC LIMIT 5'
            );
            $recent->execute([$userId, $etab]);
            $stats['recentes'] = $recent->fetchAll(\PDO::FETCH_ASSOC);
            return $stats ?: ['total' => 0, 'justifiees' => 0, 'non_justifiees' => 0, 'recentes' => []];
        } catch (\Throwable) {
            return ['total' => 0, 'justifiees' => 0, 'non_justifiees' => 0, 'recentes' => []];
        }
    }
}

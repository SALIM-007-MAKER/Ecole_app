<?php
declare(strict_types=1);

namespace App\Modules\Portals\Enseignant\Widgets;

use App\Modules\Portals\Shared\Widgets\BaseWidget;
use Core\Database;

class AbsencesClasseWidget extends BaseWidget
{
    public function getId(): string    { return 'enseignant_absences_classe'; }
    public function getTitle(): string { return 'Absences de mes classes'; }
    public function getIcon(): string  { return 'user-x'; }
    public function getPortals(): array { return ['enseignant']; }
    public function getPermissions(): array { return ['attendance.session.validate']; }
    public function getDefaultSize(): string { return 'sm'; }
    public function getDefaultOrder(): int   { return 3; }
    public function isRefreshable(): bool    { return true; }
    public function getRefreshInterval(): int { return 300; }
    public function getTemplate(): string { return 'Portals::Shared/Widgets/enseignant/absences_classe'; }

    public function getData(int $etab, int $userId, array $config): array
    {
        try {
            $pdo  = Database::getInstance()->getConnection();
            $stmt = $pdo->prepare(
                'SELECT a.date_absence, COUNT(*) AS nb,
                        c.nom AS classe_nom
                 FROM vs_absences a
                 JOIN eleves el ON el.user_id = a.eleve_user_id
                 JOIN classes c ON c.id = el.classe_id
                 JOIN rh_affectation_matieres am ON am.classe_id = c.id
                 JOIN rh_employes emp ON emp.id = am.employe_id
                 JOIN users u ON u.linked_id = emp.id
                 WHERE u.id=? AND a.etablissement_id=?
                   AND a.date_absence >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                   AND a.deleted_at IS NULL
                 GROUP BY c.id, a.date_absence ORDER BY a.date_absence DESC'
            );
            $stmt->execute([$userId, $etab]);
            $absences = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            return ['absences' => $absences, 'total' => array_sum(array_column($absences, 'nb'))];
        } catch (\Throwable) {
            return ['absences' => [], 'total' => 0];
        }
    }
}

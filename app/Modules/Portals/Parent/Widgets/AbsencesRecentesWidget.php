<?php
declare(strict_types=1);

namespace App\Modules\Portals\Parent\Widgets;

use App\Modules\Portals\Shared\Widgets\BaseWidget;
use Core\Database;

class AbsencesRecentesWidget extends BaseWidget
{
    public function getId(): string    { return 'parent_absences_recentes'; }
    public function getTitle(): string { return 'Absences récentes'; }
    public function getIcon(): string  { return 'user-x'; }
    public function getPortals(): array { return ['parent']; }
    public function getPermissions(): array { return ['absences.view_own']; }
    public function getDefaultSize(): string { return 'sm'; }
    public function getDefaultOrder(): int   { return 2; }
    public function isRefreshable(): bool    { return true; }
    public function getRefreshInterval(): int { return 300; }
    public function getTemplate(): string { return 'Portals::Shared/Widgets/parent/absences_recentes'; }

    public function getData(int $etab, int $userId, array $config): array
    {
        try {
            $pdo  = Database::getInstance()->getConnection();
            $stmt = $pdo->prepare(
                'SELECT a.date_absence, a.justifiee, CONCAT(u.prenom," ",u.nom) AS enfant_nom
                 FROM vs_absences a
                 JOIN famille_eleve fe ON fe.eleve_id = (SELECT id FROM eleves WHERE user_id = a.eleve_user_id LIMIT 1)
                 JOIN famille_membres fm ON fm.famille_id = fe.famille_id
                 JOIN users u ON u.id = a.eleve_user_id
                 WHERE fm.user_id=? AND a.etablissement_id=? AND a.deleted_at IS NULL
                 ORDER BY a.date_absence DESC LIMIT 10'
            );
            $stmt->execute([$userId, $etab]);
            $abs   = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            $injust = count(array_filter($abs, fn($a) => !$a['justifiee']));
            return ['absences' => $abs, 'non_justifiees' => $injust];
        } catch (\Throwable) {
            return ['absences' => [], 'non_justifiees' => 0];
        }
    }
}

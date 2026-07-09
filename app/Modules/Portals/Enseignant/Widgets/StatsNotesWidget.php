<?php
declare(strict_types=1);

namespace App\Modules\Portals\Enseignant\Widgets;

use App\Modules\Portals\Shared\Widgets\BaseWidget;
use Core\Database;

class StatsNotesWidget extends BaseWidget
{
    public function getId(): string    { return 'enseignant_stats_notes'; }
    public function getTitle(): string { return 'Statistiques notes'; }
    public function getIcon(): string  { return 'bar-chart'; }
    public function getPortals(): array { return ['enseignant']; }
    public function getPermissions(): array { return ['academique.notes.manage']; }
    public function getDefaultSize(): string { return 'md'; }
    public function getDefaultOrder(): int   { return 5; }
    public function getTemplate(): string { return 'Portals::Shared/Widgets/enseignant/stats_notes'; }

    public function getData(int $etab, int $userId, array $config): array
    {
        try {
            $pdo  = Database::getInstance()->getConnection();
            $stmt = $pdo->prepare(
                'SELECT m.nom AS matiere, c.nom AS classe,
                        ROUND(AVG(n.valeur),2) AS moyenne,
                        MIN(n.valeur) AS note_min,
                        MAX(n.valeur) AS note_max,
                        COUNT(n.id) AS nb_notes
                 FROM notes_v n
                 JOIN evaluations e ON e.id = n.evaluation_id
                 JOIN matieres m ON m.id = e.matiere_id
                 JOIN classes c ON c.id = e.classe_id
                 WHERE e.enseignant_user_id=? AND n.etablissement_id=? AND n.deleted_at IS NULL
                 GROUP BY e.matiere_id, e.classe_id ORDER BY m.nom, c.nom'
            );
            $stmt->execute([$userId, $etab]);
            return ['stats' => $stmt->fetchAll(\PDO::FETCH_ASSOC)];
        } catch (\Throwable) {
            return ['stats' => []];
        }
    }
}

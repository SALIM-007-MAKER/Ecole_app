<?php
declare(strict_types=1);

namespace App\Modules\Portals\Enseignant\Widgets;

use App\Modules\Portals\Shared\Widgets\BaseWidget;
use Core\Database;

class EvaluationsANoterWidget extends BaseWidget
{
    public function getId(): string    { return 'enseignant_evaluations_a_noter'; }
    public function getTitle(): string { return 'Évaluations à noter'; }
    public function getIcon(): string  { return 'edit-3'; }
    public function getPortals(): array { return ['enseignant']; }
    public function getPermissions(): array { return ['academique.notes.manage']; }
    public function getDefaultSize(): string { return 'sm'; }
    public function getDefaultOrder(): int   { return 4; }
    public function isRefreshable(): bool    { return true; }
    public function getRefreshInterval(): int { return 300; }
    public function getTemplate(): string { return 'Portals::Shared/Widgets/enseignant/evaluations_a_noter'; }

    public function getData(int $etab, int $userId, array $config): array
    {
        try {
            $pdo  = Database::getInstance()->getConnection();
            $stmt = $pdo->prepare(
                'SELECT e.id, e.titre, e.date_evaluation, m.nom AS matiere_nom, c.nom AS classe_nom,
                        COUNT(n.id) AS nb_notes_saisies,
                        (SELECT COUNT(*) FROM eleves el WHERE el.classe_id = e.classe_id AND el.deleted_at IS NULL) AS nb_eleves
                 FROM evaluations e
                 JOIN matieres m ON m.id = e.matiere_id
                 JOIN classes c ON c.id = e.classe_id
                 LEFT JOIN notes_v n ON n.evaluation_id = e.id AND n.deleted_at IS NULL
                 WHERE e.etablissement_id=? AND e.enseignant_user_id=? AND e.deleted_at IS NULL
                   AND e.date_evaluation <= CURDATE()
                 GROUP BY e.id
                 HAVING nb_notes_saisies < nb_eleves
                 ORDER BY e.date_evaluation ASC LIMIT 10'
            );
            $stmt->execute([$etab, $userId]);
            $evals = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            return ['evaluations' => $evals, 'total' => count($evals)];
        } catch (\Throwable) {
            return ['evaluations' => [], 'total' => 0];
        }
    }
}

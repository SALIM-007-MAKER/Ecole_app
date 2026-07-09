<?php
declare(strict_types=1);

namespace App\Modules\Portals\RH\Widgets;

use App\Modules\Portals\Shared\Widgets\BaseWidget;
use Core\Database;

class EvaluationsPlanifieesWidget extends BaseWidget
{
    public function getId(): string    { return 'rh_evaluations_planifiees'; }
    public function getTitle(): string { return 'Évaluations planifiées'; }
    public function getIcon(): string  { return 'clipboard'; }
    public function getPortals(): array { return ['rh']; }
    public function getPermissions(): array { return ['evaluation.manage']; }
    public function getDefaultSize(): string { return 'sm'; }
    public function getDefaultOrder(): int   { return 6; }
    public function getTemplate(): string { return 'Portals::Shared/Widgets/rh/evaluations_planifiees'; }

    public function getData(int $etab, int $userId, array $config): array
    {
        try {
            $pdo  = Database::getInstance()->getConnection();
            $stmt = $pdo->prepare(
                'SELECT ev.id, ev.date_prevue, ev.type_evaluation,
                        CONCAT(u.prenom," ",u.nom) AS employe_nom
                 FROM rh_evaluations ev
                 JOIN rh_employes emp ON emp.id = ev.employe_id
                 LEFT JOIN users u ON u.id = emp.user_id
                 WHERE ev.etablissement_id=? AND ev.statut IN ("planifiee","en_cours") AND ev.deleted_at IS NULL
                 ORDER BY ev.date_prevue ASC LIMIT 8'
            );
            $stmt->execute([$etab]);
            $evals = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            return ['evaluations' => $evals, 'total' => count($evals)];
        } catch (\Throwable) {
            return ['evaluations' => [], 'total' => 0];
        }
    }
}

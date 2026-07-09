<?php
declare(strict_types=1);

namespace App\Modules\Portals\Parent\Widgets;

use App\Modules\Portals\Shared\Widgets\BaseWidget;
use Core\Database;

class NotesRecentesParentWidget extends BaseWidget
{
    public function getId(): string    { return 'parent_notes_recentes'; }
    public function getTitle(): string { return 'Notes récentes'; }
    public function getIcon(): string  { return 'star'; }
    public function getPortals(): array { return ['parent']; }
    public function getPermissions(): array { return ['notes.view_own']; }
    public function getDefaultSize(): string { return 'md'; }
    public function getDefaultOrder(): int   { return 4; }
    public function isRefreshable(): bool    { return true; }
    public function getRefreshInterval(): int { return 300; }
    public function getTemplate(): string { return 'Portals::Shared/Widgets/parent/notes_recentes'; }

    public function getData(int $etab, int $userId, array $config): array
    {
        try {
            $pdo  = Database::getInstance()->getConnection();
            $stmt = $pdo->prepare(
                'SELECT n.valeur, n.bareme, e_eval.titre AS eval_titre, m.nom AS matiere_nom,
                        e_eval.date_evaluation, CONCAT(u.prenom," ",u.nom) AS enfant_nom
                 FROM notes_v n
                 JOIN evaluations e_eval ON e_eval.id = n.evaluation_id
                 JOIN matieres m ON m.id = e_eval.matiere_id
                 JOIN users u ON u.id = n.eleve_user_id
                 JOIN eleves el ON el.user_id = u.id
                 JOIN famille_eleve fe ON fe.eleve_id = el.id
                 JOIN famille_membres fm ON fm.famille_id = fe.famille_id
                 WHERE fm.user_id=? AND n.etablissement_id=? AND n.deleted_at IS NULL
                 ORDER BY e_eval.date_evaluation DESC LIMIT 10'
            );
            $stmt->execute([$userId, $etab]);
            return ['notes' => $stmt->fetchAll(\PDO::FETCH_ASSOC)];
        } catch (\Throwable) {
            return ['notes' => []];
        }
    }
}

<?php
declare(strict_types=1);

namespace App\Modules\Portals\Eleve\Widgets;

use App\Modules\Portals\Shared\Widgets\BaseWidget;
use Core\Database;

class NotesRecentesWidget extends BaseWidget
{
    public function getId(): string    { return 'eleve_notes_recentes'; }
    public function getTitle(): string { return 'Mes dernières notes'; }
    public function getIcon(): string  { return 'star'; }
    public function getPortals(): array { return ['eleve']; }
    public function getPermissions(): array { return ['notes.view_own']; }
    public function getDefaultSize(): string { return 'md'; }
    public function getDefaultOrder(): int   { return 2; }
    public function isRefreshable(): bool    { return true; }
    public function getRefreshInterval(): int { return 300; }
    public function getTemplate(): string { return 'Portals::Shared/Widgets/eleve/notes_recentes'; }

    public function getData(int $etab, int $userId, array $config): array
    {
        try {
            $pdo  = Database::getInstance()->getConnection();
            $stmt = $pdo->prepare(
                'SELECT n.valeur, n.bareme, e.titre AS eval_titre, m.nom AS matiere_nom, e.date_evaluation
                 FROM notes_v n
                 JOIN evaluations e ON e.id = n.evaluation_id
                 JOIN matieres m ON m.id = e.matiere_id
                 WHERE n.eleve_user_id=? AND n.etablissement_id=? AND n.deleted_at IS NULL
                 ORDER BY e.date_evaluation DESC LIMIT 8'
            );
            $stmt->execute([$userId, $etab]);
            return ['notes' => $stmt->fetchAll(\PDO::FETCH_ASSOC)];
        } catch (\Throwable) {
            return ['notes' => []];
        }
    }
}

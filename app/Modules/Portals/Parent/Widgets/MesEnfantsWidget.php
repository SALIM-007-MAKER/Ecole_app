<?php
declare(strict_types=1);

namespace App\Modules\Portals\Parent\Widgets;

use App\Modules\Portals\Shared\Widgets\BaseWidget;
use Core\Database;

class MesEnfantsWidget extends BaseWidget
{
    public function getId(): string    { return 'parent_mes_enfants'; }
    public function getTitle(): string { return 'Mes enfants'; }
    public function getIcon(): string  { return 'users'; }
    public function getPortals(): array { return ['parent']; }
    public function getDefaultSize(): string { return 'md'; }
    public function getDefaultOrder(): int   { return 1; }
    public function getTemplate(): string { return 'Portals::Shared/Widgets/parent/mes_enfants'; }

    public function getData(int $etab, int $userId, array $config): array
    {
        try {
            $pdo  = Database::getInstance()->getConnection();
            $stmt = $pdo->prepare(
                'SELECT e.id, c.nom AS classe_nom, c.niveau,
                        u.nom, u.prenom, u.photo
                 FROM famille_eleve fe
                 JOIN famille_membres fm ON fm.famille_id = fe.famille_id
                 JOIN eleves e ON e.id = fe.eleve_id
                 LEFT JOIN classes c ON c.id = e.classe_id
                 LEFT JOIN users u ON u.id = e.user_id
                 WHERE fm.user_id=? AND e.etablissement_id=? AND e.deleted_at IS NULL AND fe.deleted_at IS NULL
                 ORDER BY u.nom, u.prenom'
            );
            $stmt->execute([$userId, $etab]);
            $enfants = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            return ['enfants' => $enfants, 'total' => count($enfants)];
        } catch (\Throwable) {
            return ['enfants' => [], 'total' => 0];
        }
    }
}

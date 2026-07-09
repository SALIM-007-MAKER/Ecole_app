<?php
declare(strict_types=1);

namespace App\Modules\Portals\Parent\Widgets;

use App\Modules\Portals\Shared\Widgets\BaseWidget;
use Core\Database;

class BulletinsParentWidget extends BaseWidget
{
    public function getId(): string    { return 'parent_bulletins'; }
    public function getTitle(): string { return 'Derniers bulletins'; }
    public function getIcon(): string  { return 'file-text'; }
    public function getPortals(): array { return ['parent']; }
    public function getPermissions(): array { return ['bulletins.view']; }
    public function getDefaultSize(): string { return 'sm'; }
    public function getDefaultOrder(): int   { return 5; }
    public function getTemplate(): string { return 'Portals::Shared/Widgets/parent/bulletins'; }

    public function getData(int $etab, int $userId, array $config): array
    {
        try {
            $pdo  = Database::getInstance()->getConnection();
            $stmt = $pdo->prepare(
                'SELECT b.id, b.moyenne_generale, b.rang, b.mention,
                        p.nom AS periode_nom, CONCAT(u.prenom," ",u.nom) AS enfant_nom
                 FROM bulletins_v2 b
                 JOIN periodes_scolaires p ON p.id = b.periode_id
                 JOIN users u ON u.id = b.eleve_user_id
                 JOIN eleves el ON el.user_id = u.id
                 JOIN famille_eleve fe ON fe.eleve_id = el.id
                 JOIN famille_membres fm ON fm.famille_id = fe.famille_id
                 WHERE fm.user_id=? AND b.etablissement_id=? AND b.deleted_at IS NULL
                 ORDER BY p.date_debut DESC LIMIT 5'
            );
            $stmt->execute([$userId, $etab]);
            return ['bulletins' => $stmt->fetchAll(\PDO::FETCH_ASSOC)];
        } catch (\Throwable) {
            return ['bulletins' => []];
        }
    }
}

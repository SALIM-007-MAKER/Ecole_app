<?php
declare(strict_types=1);

namespace App\Modules\Portals\Eleve\Widgets;

use App\Modules\Portals\Shared\Widgets\BaseWidget;
use Core\Database;

class BulletinDisponibleWidget extends BaseWidget
{
    public function getId(): string    { return 'eleve_bulletin_disponible'; }
    public function getTitle(): string { return 'Mon dernier bulletin'; }
    public function getIcon(): string  { return 'file-text'; }
    public function getPortals(): array { return ['eleve']; }
    public function getPermissions(): array { return ['bulletins.view']; }
    public function getDefaultSize(): string { return 'sm'; }
    public function getDefaultOrder(): int   { return 5; }
    public function getTemplate(): string { return 'Portals::Shared/Widgets/eleve/bulletin_disponible'; }

    public function getData(int $etab, int $userId, array $config): array
    {
        try {
            $pdo  = Database::getInstance()->getConnection();
            $stmt = $pdo->prepare(
                'SELECT b.id, b.moyenne_generale, b.rang, b.mention,
                        p.nom AS periode_nom, p.annee_scolaire
                 FROM bulletins_v2 b
                 JOIN periodes_scolaires p ON p.id = b.periode_id
                 WHERE b.eleve_user_id=? AND b.etablissement_id=? AND b.deleted_at IS NULL
                 ORDER BY p.date_debut DESC LIMIT 1'
            );
            $stmt->execute([$userId, $etab]);
            $bulletin = $stmt->fetch(\PDO::FETCH_ASSOC);
            return ['bulletin' => $bulletin ?: null, 'disponible' => (bool)$bulletin];
        } catch (\Throwable) {
            return ['bulletin' => null, 'disponible' => false];
        }
    }
}

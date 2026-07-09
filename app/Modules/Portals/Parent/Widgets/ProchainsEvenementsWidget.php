<?php
declare(strict_types=1);

namespace App\Modules\Portals\Parent\Widgets;

use App\Modules\Portals\Shared\Widgets\BaseWidget;
use Core\Database;

class ProchainsEvenementsWidget extends BaseWidget
{
    public function getId(): string    { return 'parent_prochains_evenements'; }
    public function getTitle(): string { return 'Prochains événements'; }
    public function getIcon(): string  { return 'calendar'; }
    public function getPortals(): array { return ['parent']; }
    public function getDefaultSize(): string { return 'sm'; }
    public function getDefaultOrder(): int   { return 7; }
    public function getTemplate(): string { return 'Portals::Shared/Widgets/parent/prochains_evenements'; }

    public function getData(int $etab, int $userId, array $config): array
    {
        try {
            $pdo   = Database::getInstance()->getConnection();
            $today = date('Y-m-d');
            $stmt  = $pdo->prepare(
                'SELECT titre, type_activite, date_debut
                 FROM vs_activites
                 WHERE etablissement_id=? AND date_debut >= ? AND deleted_at IS NULL
                 ORDER BY date_debut ASC LIMIT 5'
            );
            $stmt->execute([$etab, $today]);
            return ['evenements' => $stmt->fetchAll(\PDO::FETCH_ASSOC)];
        } catch (\Throwable) {
            return ['evenements' => []];
        }
    }
}

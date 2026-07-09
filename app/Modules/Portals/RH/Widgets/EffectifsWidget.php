<?php
declare(strict_types=1);

namespace App\Modules\Portals\RH\Widgets;

use App\Modules\Portals\Shared\Widgets\BaseWidget;
use Core\Database;

class EffectifsWidget extends BaseWidget
{
    public function getId(): string    { return 'rh_effectifs'; }
    public function getTitle(): string { return 'Effectifs'; }
    public function getIcon(): string  { return 'users'; }
    public function getPortals(): array { return ['rh']; }
    public function getPermissions(): array { return ['employee.view']; }
    public function getDefaultSize(): string { return 'sm'; }
    public function getDefaultOrder(): int   { return 4; }
    public function getTemplate(): string { return 'Portals::Shared/Widgets/rh/effectifs'; }

    public function getData(int $etab, int $userId, array $config): array
    {
        try {
            $pdo  = Database::getInstance()->getConnection();
            $stmt = $pdo->prepare(
                'SELECT type_employe, statut, COUNT(*) AS nb FROM rh_employes
                 WHERE etablissement_id=? AND deleted_at IS NULL
                 GROUP BY type_employe, statut ORDER BY type_employe, statut'
            );
            $stmt->execute([$etab]);
            $rows  = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            $total = array_sum(array_column($rows, 'nb'));
            return ['par_type' => $rows, 'total' => $total];
        } catch (\Throwable) {
            return ['par_type' => [], 'total' => 0];
        }
    }
}

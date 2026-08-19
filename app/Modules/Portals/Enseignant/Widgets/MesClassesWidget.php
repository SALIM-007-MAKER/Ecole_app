<?php
declare(strict_types=1);

namespace App\Modules\Portals\Enseignant\Widgets;

use App\Modules\Portals\Shared\Widgets\BaseWidget;
use Core\Database;

class MesClassesWidget extends BaseWidget
{
    public function getId(): string    { return 'enseignant_mes_classes'; }
    public function getTitle(): string { return 'Mes classes'; }
    public function getIcon(): string  { return 'book-open'; }
    public function getPortals(): array { return ['enseignant']; }
    public function getPermissions(): array { return ['academique.notes.manage']; }
    public function getDefaultSize(): string { return 'sm'; }
    public function getDefaultOrder(): int   { return 2; }
    public function getTemplate(): string { return 'Portals::Shared/Widgets/enseignant/mes_classes'; }

    public function getData(int $etab, int $userId, array $config): array
    {
        try {
            $pdo  = Database::getInstance()->getConnection();
            $stmt = $pdo->prepare(
                'SELECT DISTINCT c.id, c.nom, c.niveau,
                        m.nom AS matiere_nom, COUNT(e.id) AS nb_eleves
                 FROM classes c
                 JOIN rh_affectation_matieres am ON am.classe_id = c.id
                 JOIN rh_employes emp ON emp.id = am.employe_id
                 JOIN users u ON u.linked_id = emp.id
                 JOIN matieres m ON m.id = am.matiere_id
                 LEFT JOIN eleves e ON e.classe_id = c.id AND e.deleted_at IS NULL
                 WHERE c.etablissement_id=? AND u.id=? AND c.deleted_at IS NULL
                 GROUP BY c.id, m.id ORDER BY ' . \App\Models\ClasseModel::ordreNiveauSql('c.niveau') . ', c.nom'
            );
            $stmt->execute([$etab, $userId]);
            $classes = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            return ['classes' => $classes, 'total' => count($classes)];
        } catch (\Throwable) {
            return ['classes' => [], 'total' => 0];
        }
    }
}

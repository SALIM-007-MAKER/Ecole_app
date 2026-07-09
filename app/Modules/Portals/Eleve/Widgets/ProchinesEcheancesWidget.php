<?php
declare(strict_types=1);

namespace App\Modules\Portals\Eleve\Widgets;

use App\Modules\Portals\Shared\Widgets\BaseWidget;
use Core\Database;

class ProchinesEcheancesWidget extends BaseWidget
{
    public function getId(): string    { return 'eleve_prochaines_echeances'; }
    public function getTitle(): string { return 'Prochaines échéances'; }
    public function getIcon(): string  { return 'clock'; }
    public function getPortals(): array { return ['eleve']; }
    public function getDefaultSize(): string { return 'sm'; }
    public function getDefaultOrder(): int   { return 7; }
    public function getTemplate(): string { return 'Portals::Shared/Widgets/eleve/prochaines_echeances'; }

    public function getData(int $etab, int $userId, array $config): array
    {
        try {
            $pdo       = Database::getInstance()->getConnection();
            $echeances = [];

            $evalStmt = $pdo->prepare(
                'SELECT e.titre, e.date_evaluation AS date_echeance, "evaluation" AS type, m.nom AS label
                 FROM evaluations e
                 JOIN matieres m ON m.id = e.matiere_id
                 JOIN classes c ON c.id = e.classe_id
                 WHERE c.etablissement_id=? AND e.date_evaluation >= CURDATE()
                   AND EXISTS (SELECT 1 FROM eleves el WHERE el.classe_id=c.id AND el.user_id=? AND el.deleted_at IS NULL)
                   AND e.deleted_at IS NULL
                 ORDER BY e.date_evaluation ASC LIMIT 5'
            );
            $evalStmt->execute([$etab, $userId]);
            $echeances = array_merge($echeances, $evalStmt->fetchAll(\PDO::FETCH_ASSOC));

            usort($echeances, fn($a, $b) => strtotime($a['date_echeance']) <=> strtotime($b['date_echeance']));
            return ['echeances' => array_slice($echeances, 0, 5)];
        } catch (\Throwable) {
            return ['echeances' => []];
        }
    }
}

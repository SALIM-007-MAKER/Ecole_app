<?php
declare(strict_types=1);

namespace App\Modules\Portals\Eleve\Widgets;

use App\Modules\Portals\Shared\Widgets\BaseWidget;
use Core\Database;

class EmpruntsWidget extends BaseWidget
{
    public function getId(): string    { return 'eleve_emprunts'; }
    public function getTitle(): string { return 'Mes emprunts'; }
    public function getIcon(): string  { return 'book'; }
    public function getPortals(): array { return ['eleve']; }
    public function getPermissions(): array { return ['biblio.view']; }
    public function getDefaultSize(): string { return 'sm'; }
    public function getDefaultOrder(): int   { return 4; }
    public function getTemplate(): string { return 'Portals::Shared/Widgets/eleve/emprunts'; }

    public function getData(int $etab, int $userId, array $config): array
    {
        try {
            $pdo  = Database::getInstance()->getConnection();
            $stmt = $pdo->prepare(
                'SELECT em.date_echeance, bl.titre, bl.auteur,
                        DATEDIFF(em.date_echeance, CURDATE()) AS jours_restants
                 FROM biblio_emprunts em
                 JOIN biblio_exemplaires ex ON ex.id = em.exemplaire_id
                 JOIN biblio_livres bl ON bl.id = ex.livre_id
                 WHERE em.user_id=? AND em.etablissement_id=?
                   AND em.date_retour IS NULL AND em.deleted_at IS NULL
                 ORDER BY em.date_echeance ASC LIMIT 5'
            );
            $stmt->execute([$userId, $etab]);
            $emprunts = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            $enRetard = array_sum(array_map(fn($e) => $e['jours_restants'] < 0 ? 1 : 0, $emprunts));
            return ['emprunts' => $emprunts, 'total' => count($emprunts), 'en_retard' => $enRetard];
        } catch (\Throwable) {
            return ['emprunts' => [], 'total' => 0, 'en_retard' => 0];
        }
    }
}

<?php
declare(strict_types=1);

namespace App\Modules\Portals\RH\Widgets;

use App\Modules\Portals\Shared\Widgets\BaseWidget;
use Core\Database;

class FormationsEnCoursWidget extends BaseWidget
{
    public function getId(): string    { return 'rh_formations_en_cours'; }
    public function getTitle(): string { return 'Formations en cours'; }
    public function getIcon(): string  { return 'book-open'; }
    public function getPortals(): array { return ['rh']; }
    public function getPermissions(): array { return ['training.view']; }
    public function getDefaultSize(): string { return 'sm'; }
    public function getDefaultOrder(): int   { return 5; }
    public function getTemplate(): string { return 'Portals::Shared/Widgets/rh/formations_en_cours'; }

    public function getData(int $etab, int $userId, array $config): array
    {
        try {
            $pdo  = Database::getInstance()->getConnection();
            $stmt = $pdo->prepare(
                'SELECT s.id, s.date_debut, s.date_fin, s.statut,
                        f.titre, COUNT(i.id) AS nb_inscrits
                 FROM rh_formations_sessions s
                 JOIN rh_formations_catalogue f ON f.id = s.catalogue_id
                 LEFT JOIN rh_formations_inscriptions i ON i.session_id = s.id AND i.deleted_at IS NULL
                 WHERE s.etablissement_id=? AND s.statut IN ("en_cours","planifiee") AND s.deleted_at IS NULL
                 GROUP BY s.id ORDER BY s.date_debut ASC LIMIT 5'
            );
            $stmt->execute([$etab]);
            return ['sessions' => $stmt->fetchAll(\PDO::FETCH_ASSOC)];
        } catch (\Throwable) {
            return ['sessions' => []];
        }
    }
}

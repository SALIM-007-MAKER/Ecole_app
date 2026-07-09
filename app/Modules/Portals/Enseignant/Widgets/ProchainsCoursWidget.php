<?php
declare(strict_types=1);

namespace App\Modules\Portals\Enseignant\Widgets;

use App\Modules\Portals\Shared\Widgets\BaseWidget;
use Core\Database;

class ProchainsCoursWidget extends BaseWidget
{
    public function getId(): string    { return 'enseignant_prochains_cours'; }
    public function getTitle(): string { return 'Prochains cours'; }
    public function getIcon(): string  { return 'clock'; }
    public function getPortals(): array { return ['enseignant']; }
    public function getPermissions(): array { return ['timetable.view']; }
    public function getDefaultSize(): string { return 'sm'; }
    public function getDefaultOrder(): int   { return 6; }
    public function isRefreshable(): bool    { return true; }
    public function getRefreshInterval(): int { return 300; }
    public function getTemplate(): string { return 'Portals::Shared/Widgets/enseignant/prochains_cours'; }

    public function getData(int $etab, int $userId, array $config): array
    {
        try {
            $pdo  = Database::getInstance()->getConnection();
            $jour = (int)date('N');
            $stmt = $pdo->prepare(
                'SELECT cr.jour, cr.heure_debut, cr.heure_fin,
                        c.nom AS classe_nom, m.nom AS matiere_nom, s.nom AS salle_nom
                 FROM vs_emplois_du_temps edt
                 JOIN vs_edt_creneaux cr ON cr.emploi_du_temps_id = edt.id
                 JOIN classes c ON c.id = edt.classe_id
                 JOIN matieres m ON m.id = edt.matiere_id
                 LEFT JOIN vs_edt_salles s ON s.id = cr.salle_id
                 WHERE edt.etablissement_id=? AND edt.enseignant_user_id=?
                   AND edt.deleted_at IS NULL
                   AND (cr.jour > ? OR (cr.jour = ? AND cr.heure_debut > CURTIME()))
                 ORDER BY cr.jour, cr.heure_debut LIMIT 5'
            );
            $stmt->execute([$etab, $userId, $jour, $jour]);
            return ['cours' => $stmt->fetchAll(\PDO::FETCH_ASSOC)];
        } catch (\Throwable) {
            return ['cours' => []];
        }
    }
}

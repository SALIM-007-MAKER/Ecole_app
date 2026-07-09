<?php
declare(strict_types=1);

namespace App\Modules\Portals\Enseignant\Widgets;

use App\Modules\Portals\Shared\Widgets\BaseWidget;
use Core\Database;

class MonEmploiDuTempsWidget extends BaseWidget
{
    public function getId(): string    { return 'enseignant_mon_emploi_du_temps'; }
    public function getTitle(): string { return 'Mon emploi du temps'; }
    public function getIcon(): string  { return 'calendar'; }
    public function getPortals(): array { return ['enseignant']; }
    public function getPermissions(): array { return ['timetable.view']; }
    public function getDefaultSize(): string { return 'lg'; }
    public function getDefaultOrder(): int   { return 1; }
    public function getTemplate(): string { return 'Portals::Shared/Widgets/enseignant/mon_emploi_du_temps'; }

    public function getData(int $etab, int $userId, array $config): array
    {
        try {
            $pdo  = Database::getInstance()->getConnection();
            $jour = (int)date('N'); // 1=lundi … 7=dimanche
            $stmt = $pdo->prepare(
                'SELECT cr.jour, cr.heure_debut, cr.heure_fin,
                        c.nom AS classe_nom, m.nom AS matiere_nom, s.nom AS salle_nom
                 FROM vs_emplois_du_temps edt
                 JOIN vs_edt_creneaux cr ON cr.emploi_du_temps_id = edt.id
                 JOIN classes c ON c.id = edt.classe_id
                 JOIN matieres m ON m.id = edt.matiere_id
                 LEFT JOIN vs_edt_salles s ON s.id = cr.salle_id
                 WHERE edt.etablissement_id=? AND edt.enseignant_user_id=? AND edt.deleted_at IS NULL
                 ORDER BY cr.jour, cr.heure_debut'
            );
            $stmt->execute([$etab, $userId]);
            $creneaux = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            $today    = array_values(array_filter($creneaux, fn($c) => (int)$c['jour'] === $jour));
            return ['tous' => $creneaux, 'aujourd_hui' => $today, 'jour_courant' => $jour];
        } catch (\Throwable) {
            return ['tous' => [], 'aujourd_hui' => [], 'jour_courant' => (int)date('N')];
        }
    }
}

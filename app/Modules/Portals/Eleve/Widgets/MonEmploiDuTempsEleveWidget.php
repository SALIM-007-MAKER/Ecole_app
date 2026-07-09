<?php
declare(strict_types=1);

namespace App\Modules\Portals\Eleve\Widgets;

use App\Modules\Portals\Shared\Widgets\BaseWidget;
use Core\Database;

class MonEmploiDuTempsEleveWidget extends BaseWidget
{
    public function getId(): string    { return 'eleve_mon_emploi_du_temps'; }
    public function getTitle(): string { return 'Mon emploi du temps'; }
    public function getIcon(): string  { return 'calendar'; }
    public function getPortals(): array { return ['eleve']; }
    public function getDefaultSize(): string { return 'lg'; }
    public function getDefaultOrder(): int   { return 1; }
    public function getTemplate(): string { return 'Portals::Shared/Widgets/eleve/mon_emploi_du_temps'; }

    public function getData(int $etab, int $userId, array $config): array
    {
        try {
            $pdo  = Database::getInstance()->getConnection();
            $jour = (int)date('N');
            $stmt = $pdo->prepare(
                'SELECT cr.jour, cr.heure_debut, cr.heure_fin,
                        m.nom AS matiere_nom,
                        CONCAT(u.prenom," ",u.nom) AS enseignant_nom,
                        s.nom AS salle_nom
                 FROM vs_emplois_du_temps edt
                 JOIN vs_edt_creneaux cr ON cr.emploi_du_temps_id = edt.id
                 JOIN classes c ON c.id = edt.classe_id
                 JOIN matieres m ON m.id = edt.matiere_id
                 LEFT JOIN vs_edt_salles s ON s.id = cr.salle_id
                 LEFT JOIN users u ON u.id = edt.enseignant_user_id
                 WHERE edt.etablissement_id=? AND edt.deleted_at IS NULL
                   AND EXISTS (SELECT 1 FROM eleves el WHERE el.classe_id=c.id AND el.user_id=? AND el.deleted_at IS NULL)
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

<?php
declare(strict_types=1);

namespace App\Modules\Api\Controllers\V1\VieScolaire;

use App\Modules\Api\Controllers\ResourceApiController;
use Core\Database;

class EmploiDuTempsApiController extends ResourceApiController
{
    /** GET /api/v1/emplois-du-temps */
    public function index(): void
    {
        $ctx = $this->requireApiAuth();
        $this->requireApiPermission('timetable.view');
        $this->checkRateLimit();

        $classeId  = (int)($_GET['classe_id'] ?? 0);
        $ensId     = (int)($_GET['enseignant_id'] ?? 0);
        $annee     = $_GET['annee_scolaire'] ?? null;

        $pdo        = Database::getInstance()->getConnection();
        $conditions = ['s.etablissement_id = ?'];
        $bindings   = [$ctx->etablissementId];

        if ($classeId > 0) { $conditions[] = 's.classe_id = ?'; $bindings[] = $classeId; }
        if ($ensId > 0)    { $conditions[] = 's.enseignant_id = ?'; $bindings[] = $ensId; }
        if ($annee)        { $conditions[] = 's.annee_scolaire = ?'; $bindings[] = $annee; }

        $where = 'WHERE ' . implode(' AND ', $conditions);
        $stmt  = $pdo->prepare(
            "SELECT s.*, c.nom AS classe_nom, m.nom AS matiere_nom,
                    u.nom AS enseignant_nom, sal.nom AS salle_nom
             FROM vs_schedules s
             LEFT JOIN classes c ON c.id = s.classe_id
             LEFT JOIN matieres m ON m.id = s.matiere_id
             LEFT JOIN users u ON u.id = s.enseignant_id
             LEFT JOIN vs_rooms sal ON sal.id = s.salle_id
             $where ORDER BY s.jour_semaine ASC, s.heure_debut ASC"
        );
        $stmt->execute($bindings);
        $rows  = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $items = array_map(fn($r) => [
            'id'             => (int)$r['id'],
            'jour_semaine'   => (int)$r['jour_semaine'],
            'heure_debut'    => $r['heure_debut'],
            'heure_fin'      => $r['heure_fin'],
            'classe_id'      => (int)$r['classe_id'],
            'classe_nom'     => $r['classe_nom'],
            'matiere_nom'    => $r['matiere_nom'],
            'enseignant_nom' => $r['enseignant_nom'],
            'salle_nom'      => $r['salle_nom'],
            'annee_scolaire' => $r['annee_scolaire'],
        ], $rows);

        $this->apiSuccess(['data' => $items, 'total' => count($items)]);
    }
}

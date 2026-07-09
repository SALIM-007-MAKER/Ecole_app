<?php
declare(strict_types=1);

namespace App\Modules\Api\Controllers\V1\RH;

use App\Modules\Api\Controllers\ResourceApiController;
use App\Modules\Api\Exceptions\NotFoundException;
use Core\Database;

class FormationApiController extends ResourceApiController
{
    /** GET /api/v1/formations */
    public function index(): void
    {
        $ctx = $this->requireApiAuth();
        $this->requireApiPermission('training.view');
        $this->checkRateLimit();

        $paging  = $this->parsePagination();
        $filters = $this->parseFilters(['categorie', 'statut', 'obligatoire']);
        $sort    = $this->parseSort(['titre', 'date_debut', 'created_at'], 'date_debut');

        $pdo        = Database::getInstance()->getConnection();
        $conditions = ['f.etablissement_id = ?'];
        $bindings   = [$ctx->etablissementId];
        foreach ($filters['conditions'] as $c) { $conditions[] = $c; }
        array_push($bindings, ...$filters['bindings']);

        $where = 'WHERE ' . implode(' AND ', $conditions);
        $cnt   = $pdo->prepare("SELECT COUNT(*) FROM rh_trainings f $where");
        $cnt->execute($bindings);
        $total = (int)$cnt->fetchColumn();

        $stmt = $pdo->prepare(
            "SELECT f.*,
                    (SELECT COUNT(*) FROM rh_training_participations tp WHERE tp.formation_id=f.id) AS nb_participants
             FROM rh_trainings f $where ORDER BY $sort LIMIT {$paging['per_page']} OFFSET {$paging['offset']}"
        );
        $stmt->execute($bindings);
        $rows  = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $items = array_map(fn($r) => [
            'id'             => (int)$r['id'],
            'titre'          => $r['titre'],
            'categorie'      => $r['categorie'],
            'formateur'      => $r['formateur'],
            'date_debut'     => $r['date_debut'],
            'date_fin'       => $r['date_fin'],
            'duree_heures'   => isset($r['duree_heures']) ? (float)$r['duree_heures'] : null,
            'obligatoire'    => (bool)($r['obligatoire'] ?? false),
            'statut'         => $r['statut'],
            'nb_participants' => (int)$r['nb_participants'],
            'created_at'     => $r['created_at'],
        ], $rows);

        $this->respondCollection($items, $total, $paging, '/api/v1/formations');
    }

    /** GET /api/v1/formations/{id} */
    public function show(string $id): void
    {
        $ctx = $this->requireApiAuth();
        $this->requireApiPermission('training.view');
        $row = $this->findOne((int)$id, $ctx->etablissementId);
        $this->apiSuccess($row);
    }

    private function findOne(int $id, int $etab): array
    {
        $pdo  = Database::getInstance()->getConnection();
        $stmt = $pdo->prepare('SELECT * FROM rh_trainings WHERE id=:id AND etablissement_id=:etab LIMIT 1');
        $stmt->execute([':id' => $id, ':etab' => $etab]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) throw new NotFoundException('Formation');
        return $row;
    }
}

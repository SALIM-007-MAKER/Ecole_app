<?php
declare(strict_types=1);

namespace App\Modules\Api\Controllers\V1\VieScolaire;

use App\Modules\Api\Controllers\ResourceApiController;
use App\Modules\Api\Exceptions\NotFoundException;
use Core\Database;

class ActiviteApiController extends ResourceApiController
{
    /** GET /api/v1/activites */
    public function index(): void
    {
        $ctx = $this->requireApiAuth();
        $this->requireApiPermission('activity.view');
        $this->checkRateLimit();

        $paging  = $this->parsePagination();
        $filters = $this->parseFilters(['categorie', 'statut', 'type']);
        $sort    = $this->parseSort(['nom', 'date_debut', 'created_at'], 'date_debut');

        $pdo        = Database::getInstance()->getConnection();
        $conditions = ['a.etablissement_id = ?'];
        $bindings   = [$ctx->etablissementId];
        foreach ($filters['conditions'] as $c) { $conditions[] = $c; }
        array_push($bindings, ...$filters['bindings']);

        $where = 'WHERE ' . implode(' AND ', $conditions);
        $cnt   = $pdo->prepare("SELECT COUNT(*) FROM vs_activities a $where");
        $cnt->execute($bindings);
        $total = (int)$cnt->fetchColumn();

        $stmt = $pdo->prepare(
            "SELECT a.*,
                    (SELECT COUNT(*) FROM vs_activity_inscriptions ai WHERE ai.activite_id=a.id AND ai.statut='inscrit') AS nb_inscrits
             FROM vs_activities a $where ORDER BY $sort LIMIT {$paging['per_page']} OFFSET {$paging['offset']}"
        );
        $stmt->execute($bindings);
        $rows  = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $items = array_map(fn($r) => [
            'id'              => (int)$r['id'],
            'nom'             => $r['nom'],
            'categorie'       => $r['categorie'],
            'type'            => $r['type'],
            'description'     => $r['description'],
            'date_debut'      => $r['date_debut'],
            'date_fin'        => $r['date_fin'],
            'places_max'      => isset($r['places_max']) ? (int)$r['places_max'] : null,
            'nb_inscrits'     => (int)$r['nb_inscrits'],
            'statut'          => $r['statut'],
            'created_at'      => $r['created_at'],
        ], $rows);

        $this->respondCollection($items, $total, $paging, '/api/v1/activites');
    }

    /** GET /api/v1/activites/{id} */
    public function show(string $id): void
    {
        $ctx = $this->requireApiAuth();
        $this->requireApiPermission('activity.view');
        $row = $this->findOne((int)$id, $ctx->etablissementId);
        $this->apiSuccess($row);
    }

    private function findOne(int $id, int $etab): array
    {
        $pdo  = Database::getInstance()->getConnection();
        $stmt = $pdo->prepare('SELECT * FROM vs_activities WHERE id=:id AND etablissement_id=:etab LIMIT 1');
        $stmt->execute([':id' => $id, ':etab' => $etab]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) throw new NotFoundException('Activité');
        return $row;
    }
}

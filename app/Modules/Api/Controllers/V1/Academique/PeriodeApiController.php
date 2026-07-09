<?php
declare(strict_types=1);

namespace App\Modules\Api\Controllers\V1\Academique;

use App\Modules\Api\Controllers\ResourceApiController;
use App\Modules\Api\Exceptions\NotFoundException;
use Core\Database;

class PeriodeApiController extends ResourceApiController
{
    /** GET /api/v1/periodes */
    public function index(): void
    {
        $ctx = $this->requireApiAuth();
        $this->requireApiPermission('periodes.view');
        $this->checkRateLimit();

        $paging  = $this->parsePagination();
        $filters = $this->parseFilters(['annee_scolaire', 'type', 'statut']);
        $sort    = $this->parseSort(['date_debut', 'date_fin', 'ordre'], 'date_debut');

        $pdo        = Database::getInstance()->getConnection();
        $conditions = ['p.etablissement_id = ?'];
        $bindings   = [$ctx->etablissementId];
        foreach ($filters['conditions'] as $c) { $conditions[] = $c; }
        array_push($bindings, ...$filters['bindings']);

        $where = 'WHERE ' . implode(' AND ', $conditions);
        $cnt   = $pdo->prepare("SELECT COUNT(*) FROM periodes_scolaires p $where");
        $cnt->execute($bindings);
        $total = (int)$cnt->fetchColumn();

        $stmt = $pdo->prepare(
            "SELECT p.* FROM periodes_scolaires p $where ORDER BY $sort LIMIT {$paging['per_page']} OFFSET {$paging['offset']}"
        );
        $stmt->execute($bindings);
        $rows  = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $items = array_map(fn($r) => [
            'id'            => (int)$r['id'],
            'nom'           => $r['nom'],
            'type'          => $r['type'],
            'annee_scolaire'=> $r['annee_scolaire'],
            'date_debut'    => $r['date_debut'],
            'date_fin'      => $r['date_fin'],
            'ordre'         => (int)$r['ordre'],
            'statut'        => $r['statut'],
            'created_at'    => $r['created_at'],
        ], $rows);

        $this->respondCollection($items, $total, $paging, '/api/v1/periodes');
    }

    /** GET /api/v1/periodes/{id} */
    public function show(string $id): void
    {
        $ctx = $this->requireApiAuth();
        $this->requireApiPermission('periodes.view');
        $row = $this->findOne((int)$id, $ctx->etablissementId);
        $this->apiSuccess($row);
    }

    private function findOne(int $id, int $etab): array
    {
        $pdo  = Database::getInstance()->getConnection();
        $stmt = $pdo->prepare('SELECT * FROM periodes_scolaires WHERE id=:id AND etablissement_id=:etab LIMIT 1');
        $stmt->execute([':id' => $id, ':etab' => $etab]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) throw new NotFoundException('Période');
        return $row;
    }
}

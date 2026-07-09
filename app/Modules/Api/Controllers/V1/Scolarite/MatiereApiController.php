<?php
declare(strict_types=1);

namespace App\Modules\Api\Controllers\V1\Scolarite;

use App\Modules\Api\Controllers\ResourceApiController;
use App\Modules\Api\Exceptions\NotFoundException;
use Core\Database;

class MatiereApiController extends ResourceApiController
{
    /** GET /api/v1/matieres */
    public function index(): void
    {
        $ctx = $this->requireApiAuth();
        $this->requireApiPermission('matieres.view');
        $this->checkRateLimit();

        $paging  = $this->parsePagination();
        $filters = $this->parseFilters(['actif', 'filiere', 'coefficient']);
        $sort    = $this->parseSort(['nom', 'code', 'coefficient', 'created_at'], 'nom');

        $pdo        = Database::getInstance()->getConnection();
        $conditions = ['m.etablissement_id = ?'];
        $bindings   = [$ctx->etablissementId];
        foreach ($filters['conditions'] as $c) { $conditions[] = $c; }
        array_push($bindings, ...$filters['bindings']);

        $where = 'WHERE ' . implode(' AND ', $conditions);
        $cnt   = $pdo->prepare("SELECT COUNT(*) FROM matieres m $where");
        $cnt->execute($bindings);
        $total = (int)$cnt->fetchColumn();

        $stmt = $pdo->prepare(
            "SELECT m.* FROM matieres m $where ORDER BY $sort LIMIT {$paging['per_page']} OFFSET {$paging['offset']}"
        );
        $stmt->execute($bindings);
        $rows  = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $items = array_map(fn($r) => [
            'id'          => (int)$r['id'],
            'nom'         => $r['nom'],
            'code'        => $r['code'],
            'coefficient' => (float)$r['coefficient'],
            'filiere'     => $r['filiere'] ?? null,
            'couleur'     => $r['couleur'] ?? null,
            'actif'       => (bool)$r['actif'],
            'created_at'  => $r['created_at'],
        ], $rows);

        $this->respondCollection($items, $total, $paging, '/api/v1/matieres');
    }

    /** GET /api/v1/matieres/{id} */
    public function show(string $id): void
    {
        $ctx = $this->requireApiAuth();
        $this->requireApiPermission('matieres.view');
        $row = $this->findOne((int)$id, $ctx->etablissementId);
        $this->apiSuccess($row);
    }

    private function findOne(int $id, int $etab): array
    {
        $pdo  = Database::getInstance()->getConnection();
        $stmt = $pdo->prepare('SELECT * FROM matieres WHERE id=:id AND etablissement_id=:etab LIMIT 1');
        $stmt->execute([':id' => $id, ':etab' => $etab]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) throw new NotFoundException('Matière');
        return $row;
    }
}

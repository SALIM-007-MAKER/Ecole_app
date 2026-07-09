<?php
declare(strict_types=1);

namespace App\Modules\Api\Controllers\V1\RH;

use App\Modules\Api\Controllers\ResourceApiController;
use App\Modules\Api\Exceptions\NotFoundException;
use App\Modules\Api\Resources\RH\EmployeResource;
use Core\Database;

class EmployeApiController extends ResourceApiController
{
    /** GET /api/v1/employes */
    public function index(): void
    {
        $ctx = $this->requireApiAuth();
        $this->requireApiPermission('employee.view');
        $this->checkRateLimit();

        $paging  = $this->parsePagination();
        $filters = $this->parseFilters(['statut', 'type_contrat', 'departement', 'poste']);
        $sort    = $this->parseSort(['nom', 'prenom', 'date_embauche', 'matricule'], 'nom');

        $pdo        = Database::getInstance()->getConnection();
        $conditions = ['e.etablissement_id = ?', 'e.deleted_at IS NULL'];
        $bindings   = [$ctx->etablissementId];
        foreach ($filters['conditions'] as $c) { $conditions[] = $c; }
        array_push($bindings, ...$filters['bindings']);

        $where = 'WHERE ' . implode(' AND ', $conditions);
        $cnt   = $pdo->prepare("SELECT COUNT(*) FROM rh_employees e $where");
        $cnt->execute($bindings);
        $total = (int)$cnt->fetchColumn();

        $stmt = $pdo->prepare(
            "SELECT e.* FROM rh_employees e $where ORDER BY $sort LIMIT {$paging['per_page']} OFFSET {$paging['offset']}"
        );
        $stmt->execute($bindings);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $this->respondCollection(
            EmployeResource::collection($rows, $ctx),
            $total,
            $paging,
            '/api/v1/employes'
        );
    }

    /** GET /api/v1/employes/{id} */
    public function show(string $id): void
    {
        $ctx = $this->requireApiAuth();
        $this->requireApiPermission('employee.view');
        $row = $this->findOne((int)$id, $ctx->etablissementId);
        $this->apiSuccess((new EmployeResource($row))->toArray($ctx));
    }

    private function findOne(int $id, int $etab): array
    {
        $pdo  = Database::getInstance()->getConnection();
        $stmt = $pdo->prepare('SELECT * FROM rh_employees WHERE id=:id AND etablissement_id=:etab AND deleted_at IS NULL LIMIT 1');
        $stmt->execute([':id' => $id, ':etab' => $etab]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) throw new NotFoundException('Employé');
        return $row;
    }
}

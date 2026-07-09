<?php
declare(strict_types=1);

namespace App\Modules\Api\Controllers\V1\Scolarite;

use App\Modules\Api\Controllers\ResourceApiController;
use App\Modules\Api\Exceptions\NotFoundException;
use App\Modules\Api\Resources\Scolarite\EleveResource;
use App\Modules\Scolarite\Services\EleveService;
use App\Modules\Scolarite\DTO\EleveDTO;
use Core\Database;

class EleveApiController extends ResourceApiController
{

    /** GET /api/v1/eleves */
    public function index(): void
    {
        $ctx = $this->requireApiAuth();
        $this->requireApiPermission('eleves.view');
        $this->checkRateLimit();

        $paging  = $this->parsePagination();
        $filters = $this->parseFilters(['classe_id', 'statut', 'sexe', 'annee_scolaire', 'nom', 'matricule']);
        $sort    = $this->parseSort(['nom', 'prenom', 'matricule', 'date_naissance', 'created_at'], 'nom');

        $result = $this->queryEleves($ctx->etablissementId, $paging, $filters, $sort);

        $items = EleveResource::collection($result['data'], $ctx);
        $this->respondCollection($items, $result['total'], $paging, '/api/v1/eleves');
    }

    /** GET /api/v1/eleves/{id} */
    public function show(string $id): void
    {
        $ctx = $this->requireApiAuth();
        $this->requireApiPermission('eleves.view');

        $row = $this->findEleve((int)$id, $ctx->etablissementId);
        $this->apiSuccess((new EleveResource($row))->toArray($ctx));
    }

    /** POST /api/v1/eleves */
    public function store(): void
    {
        $ctx  = $this->requireApiAuth();
        $this->requireApiPermission('eleves.create');
        $body = $this->body();
        $this->requireFields($body, ['nom', 'prenom', 'sexe', 'date_naissance', 'matricule']);

        $svc = new EleveService();
        $dto = EleveDTO::fromRequest($body);
        $id  = $svc->creer($dto, null, $ctx->userId);

        $row = $this->findEleve($id, $ctx->etablissementId);
        $this->apiCreated((new EleveResource($row))->toArray($ctx), 'Élève créé avec succès.');
    }

    /** PUT /api/v1/eleves/{id} */
    public function update(string $id): void
    {
        $ctx  = $this->requireApiAuth();
        $this->requireApiPermission('eleves.edit');
        $body = $this->body();

        $this->findEleve((int)$id, $ctx->etablissementId); // vérif existence + tenant

        $svc = new EleveService();
        $dto = EleveDTO::fromRequest($body);
        $svc->modifier((int)$id, $dto, null, $ctx->userId);

        $row = $this->findEleve((int)$id, $ctx->etablissementId);
        $this->apiSuccess((new EleveResource($row))->toArray($ctx));
    }

    /** DELETE /api/v1/eleves/{id} */
    public function destroy(string $id): void
    {
        $ctx = $this->requireApiAuth();
        $this->requireApiPermission('eleves.delete');

        $this->findEleve((int)$id, $ctx->etablissementId);

        $svc = new EleveService();
        $svc->archiver((int)$id, $ctx->userId, 'Archivage via API');
        $this->apiNoContent();
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function queryEleves(int $etab, array $paging, array $filters, string $sort): array
    {
        $pdo        = Database::getInstance()->getConnection();
        $conditions = ["e.etablissement_id = ?", "e.deleted_at IS NULL"];
        $bindings   = [$etab];

        foreach ($filters['conditions'] as $cond) { $conditions[] = $cond; }
        array_push($bindings, ...$filters['bindings']);

        $where = 'WHERE ' . implode(' AND ', $conditions);
        $count = $pdo->prepare("SELECT COUNT(*) FROM eleves e LEFT JOIN classes c ON c.id = e.classe_id $where");
        $count->execute($bindings);
        $total = (int)$count->fetchColumn();

        $stmt = $pdo->prepare(
            "SELECT e.*, c.nom AS classe_nom
             FROM eleves e LEFT JOIN classes c ON c.id = e.classe_id
             $where ORDER BY $sort LIMIT {$paging['per_page']} OFFSET {$paging['offset']}"
        );
        $stmt->execute($bindings);
        return ['data' => $stmt->fetchAll(\PDO::FETCH_ASSOC), 'total' => $total];
    }

    private function findEleve(int $id, int $etab): array
    {
        $pdo  = Database::getInstance()->getConnection();
        $stmt = $pdo->prepare(
            'SELECT e.*, c.nom AS classe_nom FROM eleves e
             LEFT JOIN classes c ON c.id = e.classe_id
             WHERE e.id = :id AND e.etablissement_id = :etab AND e.deleted_at IS NULL LIMIT 1'
        );
        $stmt->execute([':id' => $id, ':etab' => $etab]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) throw new NotFoundException('Élève');
        return $row;
    }


}

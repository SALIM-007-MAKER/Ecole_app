<?php
declare(strict_types=1);

namespace App\Modules\Api\Controllers\V1\Scolarite;

use App\Modules\Api\Controllers\ResourceApiController;
use App\Modules\Api\Exceptions\NotFoundException;
use App\Modules\Api\Resources\Scolarite\ClasseResource;
use Core\Database;

class ClasseApiController extends ResourceApiController
{
    /** GET /api/v1/classes */
    public function index(): void
    {
        $ctx = $this->requireApiAuth();
        $this->requireApiPermission('classes.view');
        $this->checkRateLimit();

        $paging  = $this->parsePagination();
        $filters = $this->parseFilters(['niveau', 'annee_scolaire', 'actif']);
        $sort    = $this->parseSort(['nom', 'niveau', 'created_at'], 'nom');

        $result = $this->queryClasses($ctx->etablissementId, $paging, $filters, $sort);
        $this->respondCollection(
            ClasseResource::collection($result['data'], $ctx),
            $result['total'],
            $paging,
            '/api/v1/classes'
        );
    }

    /** GET /api/v1/classes/{id} */
    public function show(string $id): void
    {
        $ctx = $this->requireApiAuth();
        $this->requireApiPermission('classes.view');
        $row = $this->findClasse((int)$id, $ctx->etablissementId);
        $this->apiSuccess((new ClasseResource($row))->toArray($ctx));
    }

    /** GET /api/v1/classes/{id}/eleves */
    public function eleves(string $id): void
    {
        $ctx = $this->requireApiAuth();
        $this->requireApiPermission('classes.view');
        $this->findClasse((int)$id, $ctx->etablissementId);

        $paging = $this->parsePagination();
        $pdo    = Database::getInstance()->getConnection();
        $stmt   = $pdo->prepare(
            'SELECT e.* FROM eleves e
             WHERE e.classe_id = :cid AND e.etablissement_id = :etab AND e.deleted_at IS NULL
             ORDER BY e.nom, e.prenom LIMIT :lim OFFSET :off'
        );
        $stmt->bindValue(':cid',  (int)$id,              \PDO::PARAM_INT);
        $stmt->bindValue(':etab', $ctx->etablissementId, \PDO::PARAM_INT);
        $stmt->bindValue(':lim',  $paging['per_page'],   \PDO::PARAM_INT);
        $stmt->bindValue(':off',  $paging['offset'],     \PDO::PARAM_INT);
        $stmt->execute();

        $cnt = $pdo->prepare('SELECT COUNT(*) FROM eleves WHERE classe_id=:cid AND etablissement_id=:etab AND deleted_at IS NULL');
        $cnt->execute([':cid' => (int)$id, ':etab' => $ctx->etablissementId]);

        $items = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $items[] = [
                'id'       => (int)$row['id'],
                'matricule' => $row['matricule'],
                'nom'       => $row['nom'],
                'prenom'    => $row['prenom'],
                'sexe'      => $row['sexe'],
                'statut'    => $row['statut'],
            ];
        }
        $this->respondCollection($items, (int)$cnt->fetchColumn(), $paging, "/api/v1/classes/$id/eleves");
    }

    // ── Private ───────────────────────────────────────────────────────────────

    private function queryClasses(int $etab, array $paging, array $filters, string $sort): array
    {
        $pdo        = Database::getInstance()->getConnection();
        $conditions = ['c.etablissement_id = ?', 'c.deleted_at IS NULL'];
        $bindings   = [$etab];

        foreach ($filters['conditions'] as $cond) { $conditions[] = $cond; }
        array_push($bindings, ...$filters['bindings']);

        $where = 'WHERE ' . implode(' AND ', $conditions);
        $cnt   = $pdo->prepare("SELECT COUNT(*) FROM classes c $where");
        $cnt->execute($bindings);
        $total = (int)$cnt->fetchColumn();

        $stmt = $pdo->prepare(
            "SELECT c.*,
                    (SELECT COUNT(*) FROM eleves e WHERE e.classe_id=c.id AND e.deleted_at IS NULL) AS effectif
             FROM classes c $where ORDER BY $sort LIMIT {$paging['per_page']} OFFSET {$paging['offset']}"
        );
        $stmt->execute($bindings);
        return ['data' => $stmt->fetchAll(\PDO::FETCH_ASSOC), 'total' => $total];
    }

    private function findClasse(int $id, int $etab): array
    {
        $pdo  = Database::getInstance()->getConnection();
        $stmt = $pdo->prepare('SELECT * FROM classes WHERE id=:id AND etablissement_id=:etab AND deleted_at IS NULL LIMIT 1');
        $stmt->execute([':id' => $id, ':etab' => $etab]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) throw new NotFoundException('Classe');
        return $row;
    }
}

<?php
declare(strict_types=1);

namespace App\Modules\Api\Controllers\V1\VieScolaire;

use App\Modules\Api\Controllers\ResourceApiController;
use App\Modules\Api\Exceptions\NotFoundException;
use App\Modules\Api\Resources\VieScolaire\AbsenceResource;
use Core\Database;

class AbsenceApiController extends ResourceApiController
{
    /** GET /api/v1/absences */
    public function index(): void
    {
        $ctx = $this->requireApiAuth();
        $this->requireApiPermission('attendance.view');
        $this->checkRateLimit();

        $paging  = $this->parsePagination();
        $filters = $this->parseFilters(['eleve_user_id', 'classe_id', 'justifiee', 'type']);
        $sort    = $this->parseSort(['date_absence', 'created_at'], 'date_absence');

        $pdo        = Database::getInstance()->getConnection();
        $conditions = ['a.etablissement_id = ?'];
        $bindings   = [$ctx->etablissementId];
        foreach ($filters['conditions'] as $c) { $conditions[] = $c; }
        array_push($bindings, ...$filters['bindings']);

        $where = 'WHERE ' . implode(' AND ', $conditions);
        $cnt   = $pdo->prepare("SELECT COUNT(*) FROM vs_absences a $where");
        $cnt->execute($bindings);
        $total = (int)$cnt->fetchColumn();

        $stmt = $pdo->prepare(
            "SELECT a.*, u.nom AS eleve_nom, c.nom AS classe_nom
             FROM vs_absences a
             JOIN users u ON u.id = a.eleve_user_id
             LEFT JOIN classes c ON c.id = a.classe_id
             $where ORDER BY $sort LIMIT {$paging['per_page']} OFFSET {$paging['offset']}"
        );
        $stmt->execute($bindings);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $this->respondCollection(
            AbsenceResource::collection($rows, $ctx),
            $total,
            $paging,
            '/api/v1/absences'
        );
    }

    /** GET /api/v1/absences/{id} */
    public function show(string $id): void
    {
        $ctx = $this->requireApiAuth();
        $this->requireApiPermission('attendance.view');
        $row = $this->findOne((int)$id, $ctx->etablissementId);
        $this->apiSuccess((new AbsenceResource($row))->toArray($ctx));
    }

    /** GET /api/v1/absences/stats */
    public function stats(): void
    {
        $ctx  = $this->requireApiAuth();
        $this->requireApiPermission('attendance.view');
        $annee = $_GET['annee_scolaire'] ?? null;

        $pdo    = Database::getInstance()->getConnection();
        $where  = 'WHERE etablissement_id = ?';
        $params = [$ctx->etablissementId];
        if ($annee) { $where .= ' AND annee_scolaire = ?'; $params[] = $annee; }

        $stmt = $pdo->prepare(
            "SELECT COUNT(*) AS total,
                    SUM(CASE WHEN justifiee=1 THEN 1 ELSE 0 END) AS justifiees,
                    SUM(CASE WHEN justifiee=0 THEN 1 ELSE 0 END) AS non_justifiees,
                    SUM(duree_heures) AS heures_totales
             FROM vs_absences $where"
        );
        $stmt->execute($params);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        $this->apiSuccess([
            'total'          => (int)$row['total'],
            'justifiees'     => (int)$row['justifiees'],
            'non_justifiees' => (int)$row['non_justifiees'],
            'heures_totales' => round((float)$row['heures_totales'], 1),
        ]);
    }

    private function findOne(int $id, int $etab): array
    {
        $pdo  = Database::getInstance()->getConnection();
        $stmt = $pdo->prepare(
            'SELECT a.*, u.nom AS eleve_nom, c.nom AS classe_nom
             FROM vs_absences a JOIN users u ON u.id=a.eleve_user_id LEFT JOIN classes c ON c.id=a.classe_id
             WHERE a.id=:id AND a.etablissement_id=:etab LIMIT 1'
        );
        $stmt->execute([':id' => $id, ':etab' => $etab]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) throw new NotFoundException('Absence');
        return $row;
    }
}

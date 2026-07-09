<?php
declare(strict_types=1);

namespace App\Modules\Api\Controllers\V1\RH;

use App\Modules\Api\Controllers\ResourceApiController;
use App\Modules\Api\Exceptions\NotFoundException;
use Core\Database;

class CongeApiController extends ResourceApiController
{
    /** GET /api/v1/conges */
    public function index(): void
    {
        $ctx = $this->requireApiAuth();
        $this->requireApiPermission('leave.view');
        $this->checkRateLimit();

        $paging  = $this->parsePagination();
        $filters = $this->parseFilters(['employe_id', 'type_conge', 'statut']);
        $sort    = $this->parseSort(['date_debut', 'date_demande', 'created_at'], 'date_demande');

        $pdo        = Database::getInstance()->getConnection();
        $conditions = ['c.etablissement_id = ?'];
        $bindings   = [$ctx->etablissementId];
        foreach ($filters['conditions'] as $c2) { $conditions[] = $c2; }
        array_push($bindings, ...$filters['bindings']);

        $where = 'WHERE ' . implode(' AND ', $conditions);
        $cnt   = $pdo->prepare("SELECT COUNT(*) FROM rh_leaves c $where");
        $cnt->execute($bindings);
        $total = (int)$cnt->fetchColumn();

        $stmt = $pdo->prepare(
            "SELECT c.*, e.nom AS employe_nom, e.prenom AS employe_prenom
             FROM rh_leaves c JOIN rh_employees e ON e.id=c.employe_id
             $where ORDER BY $sort LIMIT {$paging['per_page']} OFFSET {$paging['offset']}"
        );
        $stmt->execute($bindings);
        $rows  = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $items = array_map(fn($r) => [
            'id'            => (int)$r['id'],
            'employe_id'    => (int)$r['employe_id'],
            'employe_nom'   => $r['employe_nom'] . ' ' . $r['employe_prenom'],
            'type_conge'    => $r['type_conge'],
            'date_debut'    => $r['date_debut'],
            'date_fin'      => $r['date_fin'],
            'nb_jours'      => isset($r['nb_jours']) ? (int)$r['nb_jours'] : null,
            'motif'         => $r['motif'],
            'statut'        => $r['statut'],
            'date_demande'  => $r['date_demande'],
            'created_at'    => $r['created_at'],
        ], $rows);

        $this->respondCollection($items, $total, $paging, '/api/v1/conges');
    }

    /** GET /api/v1/conges/{id} */
    public function show(string $id): void
    {
        $ctx = $this->requireApiAuth();
        $this->requireApiPermission('leave.view');
        $row = $this->findOne((int)$id, $ctx->etablissementId);
        $this->apiSuccess($row);
    }

    private function findOne(int $id, int $etab): array
    {
        $pdo  = Database::getInstance()->getConnection();
        $stmt = $pdo->prepare(
            'SELECT c.*, e.nom AS employe_nom FROM rh_leaves c JOIN rh_employees e ON e.id=c.employe_id
             WHERE c.id=:id AND c.etablissement_id=:etab LIMIT 1'
        );
        $stmt->execute([':id' => $id, ':etab' => $etab]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) throw new NotFoundException('Congé');
        return $row;
    }
}

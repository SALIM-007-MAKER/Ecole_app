<?php
declare(strict_types=1);

namespace App\Modules\Api\Controllers\V1\Scolarite;

use App\Modules\Api\Controllers\ResourceApiController;
use App\Modules\Api\Exceptions\NotFoundException;
use Core\Database;

class InscriptionApiController extends ResourceApiController
{
    /** GET /api/v1/inscriptions */
    public function index(): void
    {
        $ctx = $this->requireApiAuth();
        $this->requireApiPermission('inscriptions.view');
        $this->checkRateLimit();

        $paging  = $this->parsePagination();
        $filters = $this->parseFilters(['eleve_id', 'classe_id', 'annee_scolaire', 'statut']);
        $sort    = $this->parseSort(['date_inscription', 'created_at'], 'date_inscription');

        $pdo        = Database::getInstance()->getConnection();
        $conditions = ['i.etablissement_id = ?', 'i.deleted_at IS NULL'];
        $bindings   = [$ctx->etablissementId];
        foreach ($filters['conditions'] as $c) { $conditions[] = $c; }
        array_push($bindings, ...$filters['bindings']);

        $where = 'WHERE ' . implode(' AND ', $conditions);
        $cnt   = $pdo->prepare("SELECT COUNT(*) FROM inscriptions i $where");
        $cnt->execute($bindings);
        $total = (int)$cnt->fetchColumn();

        $stmt = $pdo->prepare(
            "SELECT i.*, e.nom AS eleve_nom, e.prenom AS eleve_prenom, c.nom AS classe_nom
             FROM inscriptions i
             JOIN eleves e ON e.id = i.eleve_id
             LEFT JOIN classes c ON c.id = i.classe_id
             $where ORDER BY $sort LIMIT {$paging['per_page']} OFFSET {$paging['offset']}"
        );
        $stmt->execute($bindings);
        $rows  = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $items = array_map(fn($r) => [
            'id'              => (int)$r['id'],
            'eleve_id'        => (int)$r['eleve_id'],
            'eleve_nom'       => $r['eleve_nom'] . ' ' . $r['eleve_prenom'],
            'classe_id'       => (int)$r['classe_id'],
            'classe_nom'      => $r['classe_nom'],
            'annee_scolaire'  => $r['annee_scolaire'],
            'date_inscription'=> $r['date_inscription'],
            'statut'          => $r['statut'],
            'created_at'      => $r['created_at'],
        ], $rows);

        $this->respondCollection($items, $total, $paging, '/api/v1/inscriptions');
    }

    /** GET /api/v1/inscriptions/{id} */
    public function show(string $id): void
    {
        $ctx  = $this->requireApiAuth();
        $this->requireApiPermission('inscriptions.view');
        $row  = $this->findOne((int)$id, $ctx->etablissementId);
        $this->apiSuccess($row);
    }

    // ── Private ───────────────────────────────────────────────────────────────

    private function findOne(int $id, int $etab): array
    {
        $pdo  = Database::getInstance()->getConnection();
        $stmt = $pdo->prepare(
            'SELECT i.*, e.nom AS eleve_nom, e.prenom AS eleve_prenom, c.nom AS classe_nom
             FROM inscriptions i
             JOIN eleves e ON e.id = i.eleve_id
             LEFT JOIN classes c ON c.id = i.classe_id
             WHERE i.id=:id AND i.etablissement_id=:etab AND i.deleted_at IS NULL LIMIT 1'
        );
        $stmt->execute([':id' => $id, ':etab' => $etab]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) throw new NotFoundException('Inscription');
        return $row;
    }
}

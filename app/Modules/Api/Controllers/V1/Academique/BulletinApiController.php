<?php
declare(strict_types=1);

namespace App\Modules\Api\Controllers\V1\Academique;

use App\Modules\Api\Controllers\ResourceApiController;
use App\Modules\Api\Exceptions\NotFoundException;
use Core\Database;

class BulletinApiController extends ResourceApiController
{
    /** GET /api/v1/bulletins */
    public function index(): void
    {
        $ctx = $this->requireApiAuth();
        $this->requireApiPermission('bulletins.view');
        $this->checkRateLimit();

        $paging  = $this->parsePagination();
        $filters = $this->parseFilters(['eleve_id', 'classe_id', 'periode_id', 'annee_scolaire', 'statut']);
        $sort    = $this->parseSort(['moyenne_generale', 'rang', 'created_at'], 'created_at');

        $pdo        = Database::getInstance()->getConnection();
        $conditions = ['b.etablissement_id = ?'];
        $bindings   = [$ctx->etablissementId];
        foreach ($filters['conditions'] as $c) { $conditions[] = $c; }
        array_push($bindings, ...$filters['bindings']);

        $where = 'WHERE ' . implode(' AND ', $conditions);
        $cnt   = $pdo->prepare("SELECT COUNT(*) FROM bulletins_v2 b $where");
        $cnt->execute($bindings);
        $total = (int)$cnt->fetchColumn();

        $stmt = $pdo->prepare(
            "SELECT b.*, e.nom AS eleve_nom, e.prenom AS eleve_prenom,
                    c.nom AS classe_nom, p.nom AS periode_nom
             FROM bulletins_v2 b
             JOIN eleves e ON e.id = b.eleve_id
             LEFT JOIN classes c ON c.id = b.classe_id
             LEFT JOIN periodes_scolaires p ON p.id = b.periode_id
             $where ORDER BY $sort LIMIT {$paging['per_page']} OFFSET {$paging['offset']}"
        );
        $stmt->execute($bindings);
        $rows  = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $items = array_map(fn($r) => [
            'id'               => (int)$r['id'],
            'eleve_id'         => (int)$r['eleve_id'],
            'eleve_nom'        => $r['eleve_nom'] . ' ' . $r['eleve_prenom'],
            'classe_nom'       => $r['classe_nom'],
            'periode_nom'      => $r['periode_nom'],
            'annee_scolaire'   => $r['annee_scolaire'],
            'moyenne_generale' => isset($r['moyenne_generale']) ? round((float)$r['moyenne_generale'], 2) : null,
            'rang'             => isset($r['rang']) ? (int)$r['rang'] : null,
            'mention'          => $r['mention'] ?? null,
            'statut'           => $r['statut'],
            'created_at'       => $r['created_at'],
        ], $rows);

        $this->respondCollection($items, $total, $paging, '/api/v1/bulletins');
    }

    /** GET /api/v1/bulletins/{id} */
    public function show(string $id): void
    {
        $ctx = $this->requireApiAuth();
        $this->requireApiPermission('bulletins.view');
        $row = $this->findOne((int)$id, $ctx->etablissementId);
        $this->apiSuccess($row);
    }

    /** GET /api/v1/bulletins/{id}/export — HTML renderable A4 */
    public function export(string $id): void
    {
        $ctx = $this->requireApiAuth();
        $this->requireApiPermission('bulletins.export');
        $row = $this->findOne((int)$id, $ctx->etablissementId);
        // Délègue au BulletinGenerator existant
        $this->apiSuccess([
            'bulletin_id' => (int)$id,
            'export_url'  => '/v2/academique/bulletins/' . $id . '/export',
            'message'     => 'Utilisez le endpoint HTML pour le rendu A4.',
        ]);
    }

    private function findOne(int $id, int $etab): array
    {
        $pdo  = Database::getInstance()->getConnection();
        $stmt = $pdo->prepare(
            'SELECT b.*, e.nom AS eleve_nom, e.prenom AS eleve_prenom, c.nom AS classe_nom
             FROM bulletins_v2 b
             JOIN eleves e ON e.id=b.eleve_id
             LEFT JOIN classes c ON c.id=b.classe_id
             WHERE b.id=:id AND b.etablissement_id=:etab LIMIT 1'
        );
        $stmt->execute([':id' => $id, ':etab' => $etab]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) throw new NotFoundException('Bulletin');
        return $row;
    }
}

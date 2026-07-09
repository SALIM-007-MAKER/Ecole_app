<?php
declare(strict_types=1);

namespace App\Modules\Api\Controllers\V1\Inventaire;

use App\Modules\Api\Controllers\ResourceApiController;
use App\Modules\Api\Exceptions\NotFoundException;
use Core\Database;

class ArticleApiController extends ResourceApiController
{
    /** GET /api/v1/articles */
    public function index(): void
    {
        $ctx = $this->requireApiAuth();
        $this->requireApiPermission('inventory.view');
        $this->checkRateLimit();

        $paging  = $this->parsePagination();
        $filters = $this->parseFilters(['categorie_id', 'statut', 'emplacement_id']);
        $sort    = $this->parseSort(['nom', 'code_article', 'stock_actuel', 'created_at'], 'nom');

        $pdo        = Database::getInstance()->getConnection();
        $conditions = ['a.etablissement_id = ?'];
        $bindings   = [$ctx->etablissementId];
        foreach ($filters['conditions'] as $c) { $conditions[] = $c; }
        array_push($bindings, ...$filters['bindings']);

        $where = 'WHERE ' . implode(' AND ', $conditions);
        $cnt   = $pdo->prepare("SELECT COUNT(*) FROM inv_articles a $where");
        $cnt->execute($bindings);
        $total = (int)$cnt->fetchColumn();

        $stmt = $pdo->prepare(
            "SELECT a.*, c.nom AS categorie_nom
             FROM inv_articles a LEFT JOIN inv_categories c ON c.id=a.categorie_id
             $where ORDER BY $sort LIMIT {$paging['per_page']} OFFSET {$paging['offset']}"
        );
        $stmt->execute($bindings);
        $rows  = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $items = array_map(fn($r) => [
            'id'              => (int)$r['id'],
            'code_article'    => $r['code_article'],
            'nom'             => $r['nom'],
            'categorie'       => $r['categorie_nom'],
            'stock_actuel'    => (int)$r['stock_actuel'],
            'stock_minimum'   => (int)$r['stock_minimum'],
            'stock_alerte'    => (bool)($r['stock_actuel'] <= $r['stock_minimum']),
            'unite'           => $r['unite'],
            'statut'          => $r['statut'],
            'valeur_unitaire' => isset($r['valeur_unitaire']) ? round((float)$r['valeur_unitaire'], 2) : null,
            'created_at'      => $r['created_at'],
        ], $rows);

        $this->respondCollection($items, $total, $paging, '/api/v1/articles');
    }

    /** GET /api/v1/articles/{id} */
    public function show(string $id): void
    {
        $ctx = $this->requireApiAuth();
        $this->requireApiPermission('inventory.view');
        $row = $this->findOne((int)$id, $ctx->etablissementId);
        $this->apiSuccess($row);
    }

    /** GET /api/v1/articles/alertes-stock */
    public function alertesStock(): void
    {
        $ctx = $this->requireApiAuth();
        $this->requireApiPermission('inventory.view');

        $pdo  = Database::getInstance()->getConnection();
        $stmt = $pdo->prepare(
            "SELECT a.id, a.code_article, a.nom, a.stock_actuel, a.stock_minimum
             FROM inv_articles a
             WHERE a.etablissement_id=:etab AND a.stock_actuel <= a.stock_minimum AND a.statut='actif'
             ORDER BY (a.stock_actuel - a.stock_minimum) ASC"
        );
        $stmt->execute([':etab' => $ctx->etablissementId]);
        $this->apiSuccess(['data' => $stmt->fetchAll(\PDO::FETCH_ASSOC)]);
    }

    private function findOne(int $id, int $etab): array
    {
        $pdo  = Database::getInstance()->getConnection();
        $stmt = $pdo->prepare('SELECT * FROM inv_articles WHERE id=:id AND etablissement_id=:etab LIMIT 1');
        $stmt->execute([':id' => $id, ':etab' => $etab]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) throw new NotFoundException('Article');
        return $row;
    }
}

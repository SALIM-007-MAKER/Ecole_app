<?php
declare(strict_types=1);

namespace App\Modules\Api\Controllers\V1\Rapports;

use App\Modules\Api\Controllers\ResourceApiController;
use App\Modules\Api\Exceptions\NotFoundException;
use Core\Database;

class RapportApiController extends ResourceApiController
{
    /** GET /api/v1/rapports */
    public function index(): void
    {
        $ctx = $this->requireApiAuth();
        $this->requireApiPermission('bi.report.view');
        $this->checkRateLimit();

        $paging  = $this->parsePagination();
        $filters = $this->parseFilters(['type', 'statut', 'created_by']);
        $sort    = $this->parseSort(['nom', 'created_at', 'type'], 'created_at');

        $pdo        = Database::getInstance()->getConnection();
        $conditions = ['r.etablissement_id = ?'];
        $bindings   = [$ctx->etablissementId];
        foreach ($filters['conditions'] as $c) { $conditions[] = $c; }
        array_push($bindings, ...$filters['bindings']);

        $where = 'WHERE ' . implode(' AND ', $conditions);
        $cnt   = $pdo->prepare("SELECT COUNT(*) FROM bi_reports r $where");
        $cnt->execute($bindings);
        $total = (int)$cnt->fetchColumn();

        $stmt = $pdo->prepare(
            "SELECT r.*, u.nom AS auteur_nom FROM bi_reports r LEFT JOIN users u ON u.id=r.created_by
             $where ORDER BY $sort LIMIT {$paging['per_page']} OFFSET {$paging['offset']}"
        );
        $stmt->execute($bindings);
        $rows  = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $items = array_map(fn($r) => [
            'id'         => (int)$r['id'],
            'nom'        => $r['nom'],
            'type'       => $r['type'],
            'statut'     => $r['statut'],
            'auteur'     => $r['auteur_nom'],
            'created_at' => $r['created_at'],
        ], $rows);

        $this->respondCollection($items, $total, $paging, '/api/v1/rapports');
    }

    /** GET /api/v1/rapports/{id} */
    public function show(string $id): void
    {
        $ctx = $this->requireApiAuth();
        $this->requireApiPermission('bi.report.view');
        $row = $this->findOne((int)$id, $ctx->etablissementId);
        $this->apiSuccess($row);
    }

    /** GET /api/v1/rapports/dashboard — métriques agrégées */
    public function dashboard(): void
    {
        $ctx   = $this->requireApiAuth();
        $this->requireApiPermission('bi.report.view');
        $annee = $_GET['annee_scolaire'] ?? null;

        $pdo    = Database::getInstance()->getConnection();
        $eWhere = 'WHERE etablissement_id = :etab';
        $p      = [':etab' => $ctx->etablissementId];
        if ($annee) { $eWhere .= ' AND annee_scolaire = :ann'; $p[':ann'] = $annee; }

        $eleves = $pdo->prepare("SELECT COUNT(*) FROM eleves $eWhere AND deleted_at IS NULL");
        $eleves->execute($p);

        $classes = $pdo->prepare("SELECT COUNT(*) FROM classes $eWhere AND deleted_at IS NULL");
        $classes->execute($p);

        $employes = $pdo->prepare('SELECT COUNT(*) FROM rh_employees WHERE etablissement_id=:etab AND deleted_at IS NULL');
        $employes->execute([':etab' => $ctx->etablissementId]);

        $this->apiSuccess([
            'nb_eleves'        => (int)$eleves->fetchColumn(),
            'nb_classes'       => (int)$classes->fetchColumn(),
            'nb_employes'      => (int)$employes->fetchColumn(),
            'annee_scolaire'   => $annee,
        ]);
    }

    private function findOne(int $id, int $etab): array
    {
        $pdo  = Database::getInstance()->getConnection();
        $stmt = $pdo->prepare('SELECT * FROM bi_reports WHERE id=:id AND etablissement_id=:etab LIMIT 1');
        $stmt->execute([':id' => $id, ':etab' => $etab]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) throw new NotFoundException('Rapport');
        return $row;
    }
}

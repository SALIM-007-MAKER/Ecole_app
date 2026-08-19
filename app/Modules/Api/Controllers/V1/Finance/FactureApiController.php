<?php
declare(strict_types=1);

namespace App\Modules\Api\Controllers\V1\Finance;

use App\Modules\Api\Controllers\ResourceApiController;
use App\Modules\Api\Exceptions\NotFoundException;
use App\Modules\Api\Resources\Finance\FactureResource;
use Core\Database;

class FactureApiController extends ResourceApiController
{
    /** GET /api/v1/factures */
    public function index(): void
    {
        $ctx = $this->requireApiAuth();
        $this->requireApiPermission('factures.view');
        $this->checkRateLimit();

        $paging       = $this->parsePagination();
        $filtersF     = $this->parseFilters(['eleve_id', 'statut', 'annee_scolaire'], 'f');
        $filtersEleve = $this->parseFilters(['classe_id'], 'e');
        $sort         = $this->parseSort(['date_emission', 'montant_total', 'numero', 'created_at'], 'date_emission', 'f');

        $pdo        = Database::getInstance()->getConnection();
        $conditions = ['e.etablissement_id = ?'];
        $bindings   = [$ctx->etablissementId];
        foreach ([...$filtersF['conditions'], ...$filtersEleve['conditions']] as $c) { $conditions[] = $c; }
        array_push($bindings, ...$filtersF['bindings'], ...$filtersEleve['bindings']);

        $where = 'WHERE ' . implode(' AND ', $conditions);
        $cnt   = $pdo->prepare(
            "SELECT COUNT(*) FROM finance_factures f
             LEFT JOIN eleves e ON e.id = f.eleve_id
             $where"
        );
        $cnt->execute($bindings);
        $total = (int)$cnt->fetchColumn();

        $stmt = $pdo->prepare(
            "SELECT f.*, e.nom AS eleve_nom, e.etablissement_id,
                    (f.montant_total - f.montant_paye) AS reste_a_payer
             FROM finance_factures f LEFT JOIN eleves e ON e.id = f.eleve_id
             $where ORDER BY $sort LIMIT {$paging['per_page']} OFFSET {$paging['offset']}"
        );
        $stmt->execute($bindings);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $this->respondCollection(
            FactureResource::collection($rows, $ctx),
            $total,
            $paging,
            '/api/v1/factures'
        );
    }

    /** GET /api/v1/factures/{id} */
    public function show(string $id): void
    {
        $ctx = $this->requireApiAuth();
        $this->requireApiPermission('factures.view');
        $row = $this->findOne((int)$id, $ctx->etablissementId);
        $this->apiSuccess((new FactureResource($row))->toArray($ctx));
    }

    /** GET /api/v1/factures/impayes — dashboard impayés */
    public function impayes(): void
    {
        $ctx = $this->requireApiAuth();
        $this->requireApiPermission('factures.view');
        $this->checkRateLimit();

        $annee  = $_GET['annee_scolaire'] ?? null;
        $pdo    = Database::getInstance()->getConnection();
        $where  = 'WHERE e.etablissement_id = ? AND f.statut IN (?,?,?)';
        $params = [$ctx->etablissementId, 'emise', 'partiellement_payee', 'en_retard'];
        if ($annee) { $where .= ' AND f.annee_scolaire = ?'; $params[] = $annee; }

        $stmt = $pdo->prepare(
            "SELECT COUNT(*) AS nb, SUM(f.montant_total - f.montant_paye) AS total_impaye
             FROM finance_factures f
             LEFT JOIN eleves e ON e.id = f.eleve_id
             $where"
        );
        $stmt->execute($params);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        $this->apiSuccess([
            'nb_impayes'    => (int)$row['nb'],
            'total_impaye'  => round((float)$row['total_impaye'], 2),
        ]);
    }

    private function findOne(int $id, int $etab): array
    {
        $pdo  = Database::getInstance()->getConnection();
        $stmt = $pdo->prepare(
            'SELECT f.*, e.nom AS eleve_nom, e.etablissement_id,
                    (f.montant_total - f.montant_paye) AS reste_a_payer
             FROM finance_factures f LEFT JOIN eleves e ON e.id=f.eleve_id
             WHERE f.id=:id AND e.etablissement_id=:etab LIMIT 1'
        );
        $stmt->execute([':id' => $id, ':etab' => $etab]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) throw new NotFoundException('Facture');
        return $row;
    }
}

<?php
declare(strict_types=1);

namespace App\Modules\Api\Controllers\V1\Finance;

use App\Modules\Api\Controllers\ResourceApiController;
use App\Modules\Api\Exceptions\NotFoundException;
use App\Modules\Api\Resources\Finance\PaiementResource;
use Core\Database;

class PaiementApiController extends ResourceApiController
{
    /** GET /api/v1/paiements */
    public function index(): void
    {
        $ctx = $this->requireApiAuth();
        $this->requireApiPermission('paiements.view');
        $this->checkRateLimit();

        $paging  = $this->parsePagination();
        $filters = $this->parseFilters(['facture_id', 'mode_paiement', 'statut', 'annee_scolaire']);
        $sort    = $this->parseSort(['date_paiement', 'montant', 'created_at'], 'date_paiement');

        $pdo        = Database::getInstance()->getConnection();
        $conditions = ['p.etablissement_id = ?'];
        $bindings   = [$ctx->etablissementId];
        foreach ($filters['conditions'] as $c) { $conditions[] = $c; }
        array_push($bindings, ...$filters['bindings']);

        $where = 'WHERE ' . implode(' AND ', $conditions);
        $cnt   = $pdo->prepare("SELECT COUNT(*) FROM finance_payments p $where");
        $cnt->execute($bindings);
        $total = (int)$cnt->fetchColumn();

        $stmt = $pdo->prepare(
            "SELECT p.*, f.numero AS facture_numero, e.nom AS eleve_nom
             FROM finance_payments p
             LEFT JOIN finance_invoices f ON f.id = p.invoice_id
             LEFT JOIN eleves e ON e.id = f.eleve_id
             $where ORDER BY $sort LIMIT {$paging['per_page']} OFFSET {$paging['offset']}"
        );
        $stmt->execute($bindings);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $this->respondCollection(
            PaiementResource::collection($rows, $ctx),
            $total,
            $paging,
            '/api/v1/paiements'
        );
    }

    /** GET /api/v1/paiements/{id} */
    public function show(string $id): void
    {
        $ctx = $this->requireApiAuth();
        $this->requireApiPermission('paiements.view');
        $row = $this->findOne((int)$id, $ctx->etablissementId);
        $this->apiSuccess((new PaiementResource($row))->toArray($ctx));
    }

    /** GET /api/v1/paiements/stats — chiffre d'affaires par période */
    public function stats(): void
    {
        $ctx  = $this->requireApiAuth();
        $this->requireApiPermission('paiements.view');
        $annee = $_GET['annee_scolaire'] ?? date('Y') . '-' . (date('Y') + 1);

        $pdo  = Database::getInstance()->getConnection();
        $stmt = $pdo->prepare(
            "SELECT DATE_FORMAT(date_paiement, '%Y-%m') AS mois,
                    SUM(montant) AS total, COUNT(*) AS nb_paiements
             FROM finance_payments
             WHERE etablissement_id = :etab AND annee_scolaire = :ann AND statut = 'valide'
             GROUP BY mois ORDER BY mois ASC"
        );
        $stmt->execute([':etab' => $ctx->etablissementId, ':ann' => $annee]);
        $this->apiSuccess([
            'annee_scolaire' => $annee,
            'par_mois'       => $stmt->fetchAll(\PDO::FETCH_ASSOC),
        ]);
    }

    private function findOne(int $id, int $etab): array
    {
        $pdo  = Database::getInstance()->getConnection();
        $stmt = $pdo->prepare(
            'SELECT p.*, f.numero AS facture_numero, e.nom AS eleve_nom
             FROM finance_payments p
             LEFT JOIN finance_invoices f ON f.id=p.invoice_id
             LEFT JOIN eleves e ON e.id=f.eleve_id
             WHERE p.id=:id AND p.etablissement_id=:etab LIMIT 1'
        );
        $stmt->execute([':id' => $id, ':etab' => $etab]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) throw new NotFoundException('Paiement');
        return $row;
    }
}

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
        $filters = $this->parseFilters(['facture_id', 'statut'], 'p');
        $sort    = $this->parseSort(['date_paiement', 'montant', 'created_at'], 'date_paiement', 'p');

        $pdo        = Database::getInstance()->getConnection();
        $conditions = ['e.etablissement_id = ?'];
        $bindings   = [$ctx->etablissementId];
        foreach ($filters['conditions'] as $c) { $conditions[] = $c; }
        array_push($bindings, ...$filters['bindings']);

        $where = 'WHERE ' . implode(' AND ', $conditions);
        $cnt   = $pdo->prepare(
            "SELECT COUNT(*) FROM finance_paiements p
             LEFT JOIN finance_factures f ON f.id = p.facture_id
             LEFT JOIN eleves e ON e.id = f.eleve_id
             $where"
        );
        $cnt->execute($bindings);
        $total = (int)$cnt->fetchColumn();

        $stmt = $pdo->prepare(
            "SELECT p.*, p.reference_externe AS reference, e.etablissement_id,
                    f.numero AS facture_numero, e.nom AS eleve_nom,
                    mp.code AS mode_paiement, r.numero AS recu_numero
             FROM finance_paiements p
             LEFT JOIN finance_factures f ON f.id = p.facture_id
             LEFT JOIN eleves e ON e.id = f.eleve_id
             LEFT JOIN finance_modes_paiement mp ON mp.id = p.mode_paiement_id
             LEFT JOIN finance_recus r ON r.paiement_id = p.id
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
            "SELECT DATE_FORMAT(p.date_paiement, '%Y-%m') AS mois,
                    SUM(p.montant_applique) AS total, COUNT(*) AS nb_paiements
             FROM finance_paiements p
             JOIN finance_factures f ON f.id = p.facture_id
             JOIN eleves e ON e.id = f.eleve_id
             WHERE e.etablissement_id = :etab AND f.annee_scolaire = :ann AND p.statut IN ('valide','complete')
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
            'SELECT p.*, p.reference_externe AS reference, e.etablissement_id,
                    f.numero AS facture_numero, e.nom AS eleve_nom,
                    mp.code AS mode_paiement, r.numero AS recu_numero
             FROM finance_paiements p
             LEFT JOIN finance_factures f ON f.id=p.facture_id
             LEFT JOIN eleves e ON e.id=f.eleve_id
             LEFT JOIN finance_modes_paiement mp ON mp.id = p.mode_paiement_id
             LEFT JOIN finance_recus r ON r.paiement_id = p.id
             WHERE p.id=:id AND e.etablissement_id=:etab LIMIT 1'
        );
        $stmt->execute([':id' => $id, ':etab' => $etab]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) throw new NotFoundException('Paiement');
        return $row;
    }
}

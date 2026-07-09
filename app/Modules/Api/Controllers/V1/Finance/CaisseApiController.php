<?php
declare(strict_types=1);

namespace App\Modules\Api\Controllers\V1\Finance;

use App\Modules\Api\Controllers\ResourceApiController;
use App\Modules\Api\Exceptions\NotFoundException;
use Core\Database;

class CaisseApiController extends ResourceApiController
{
    /** GET /api/v1/caisse/solde */
    public function solde(): void
    {
        $ctx = $this->requireApiAuth();
        $this->requireApiPermission('caisse.view');

        $pdo  = Database::getInstance()->getConnection();
        $stmt = $pdo->prepare(
            'SELECT cr.*, u.nom AS responsable_nom
             FROM finance_cash_registers cr LEFT JOIN users u ON u.id=cr.responsable_id
             WHERE cr.etablissement_id=:etab ORDER BY cr.id DESC LIMIT 1'
        );
        $stmt->execute([':etab' => $ctx->etablissementId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) {
            $this->apiSuccess(['solde' => 0, 'message' => 'Aucune caisse configurée.']);
            return;
        }
        $this->apiSuccess([
            'caisse_id'      => (int)$row['id'],
            'nom'            => $row['nom'],
            'solde_actuel'   => round((float)$row['solde_actuel'], 2),
            'responsable'    => $row['responsable_nom'],
            'statut'         => $row['statut'],
            'derniere_maj'   => $row['updated_at'],
        ]);
    }

    /** GET /api/v1/caisse/mouvements */
    public function mouvements(): void
    {
        $ctx = $this->requireApiAuth();
        $this->requireApiPermission('caisse.view');
        $this->checkRateLimit();

        $paging  = $this->parsePagination();
        $filters = $this->parseFilters(['type', 'caisse_id']);
        $sort    = $this->parseSort(['date_mouvement', 'montant', 'created_at'], 'date_mouvement');

        $pdo        = Database::getInstance()->getConnection();
        $conditions = ['m.etablissement_id = ?'];
        $bindings   = [$ctx->etablissementId];
        foreach ($filters['conditions'] as $c) { $conditions[] = $c; }
        array_push($bindings, ...$filters['bindings']);

        $where = 'WHERE ' . implode(' AND ', $conditions);
        $cnt   = $pdo->prepare("SELECT COUNT(*) FROM finance_cash_movements m $where");
        $cnt->execute($bindings);
        $total = (int)$cnt->fetchColumn();

        $stmt = $pdo->prepare(
            "SELECT m.* FROM finance_cash_movements m
             $where ORDER BY $sort LIMIT {$paging['per_page']} OFFSET {$paging['offset']}"
        );
        $stmt->execute($bindings);
        $rows  = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $items = array_map(fn($r) => [
            'id'             => (int)$r['id'],
            'caisse_id'      => (int)$r['caisse_id'],
            'type'           => $r['type'],
            'montant'        => round((float)$r['montant'], 2),
            'motif'          => $r['motif'],
            'date_mouvement' => $r['date_mouvement'],
            'created_at'     => $r['created_at'],
        ], $rows);

        $this->respondCollection($items, $total, $paging, '/api/v1/caisse/mouvements');
    }
}

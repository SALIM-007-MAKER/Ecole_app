<?php
declare(strict_types=1);

namespace App\Modules\Api\Controllers\V1\Finance;

use App\Modules\Api\Controllers\ResourceApiController;
use App\Modules\Api\Exceptions\NotFoundException;
use Core\Database;

class CaisseApiController extends ResourceApiController
{
    /**
     * GET /api/v1/caisse/solde — session de caisse ouverte la plus récente.
     * V2 modélise la caisse par sessions journalières (ouverture/fermeture),
     * pas par un registre persistant à solde courant — il n'existe donc pas
     * de "solde_actuel" unique hors session active.
     */
    public function solde(): void
    {
        $ctx = $this->requireApiAuth();
        $this->requireApiPermission('caisse.view');

        $pdo  = Database::getInstance()->getConnection();
        $stmt = $pdo->prepare(
            "SELECT sc.*, u.nom AS caissier_nom
             FROM finance_sessions_caisse sc
             LEFT JOIN users u ON u.id = sc.caissier_id
             WHERE u.etablissement_id = :etab AND sc.deleted_at IS NULL
             ORDER BY sc.id DESC LIMIT 1"
        );
        $stmt->execute([':etab' => $ctx->etablissementId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) {
            $this->apiSuccess(['statut' => 'aucune_session', 'message' => 'Aucune session de caisse trouvée.']);
            return;
        }
        $this->apiSuccess([
            'session_id'          => (int)$row['id'],
            'numero'               => $row['numero'],
            'statut'                => $row['statut'],
            'caissier'              => $row['caissier_nom'],
            'solde_initial'         => round((float)$row['solde_initial'], 2),
            'solde_theorique'       => round((float)$row['solde_theorique'], 2),
            'solde_reel'            => $row['solde_reel'] !== null ? round((float)$row['solde_reel'], 2) : null,
            'ecart'                 => $row['ecart'] !== null ? round((float)$row['ecart'], 2) : null,
            'date_ouverture'        => $row['date_ouverture'],
            'date_fermeture'        => $row['date_fermeture'],
            'derniere_maj'          => $row['updated_at'],
        ]);
    }

    /** GET /api/v1/caisse/mouvements */
    public function mouvements(): void
    {
        $ctx = $this->requireApiAuth();
        $this->requireApiPermission('caisse.view');
        $this->checkRateLimit();

        $paging  = $this->parsePagination();
        $filters = $this->parseFilters(['type', 'session_id'], 'm');
        $sort    = $this->parseSort(['created_at', 'montant'], 'created_at', 'm');

        $pdo        = Database::getInstance()->getConnection();
        $conditions = ['u.etablissement_id = ?'];
        $bindings   = [$ctx->etablissementId];
        foreach ($filters['conditions'] as $c) { $conditions[] = $c; }
        array_push($bindings, ...$filters['bindings']);

        $where = 'WHERE ' . implode(' AND ', $conditions);
        $joins = "FROM finance_mouvements_caisse m
                  JOIN finance_sessions_caisse sc ON sc.id = m.session_id
                  LEFT JOIN users u ON u.id = sc.caissier_id";

        $cnt = $pdo->prepare("SELECT COUNT(*) $joins $where");
        $cnt->execute($bindings);
        $total = (int)$cnt->fetchColumn();

        $stmt = $pdo->prepare(
            "SELECT m.* $joins
             $where ORDER BY $sort LIMIT {$paging['per_page']} OFFSET {$paging['offset']}"
        );
        $stmt->execute($bindings);
        $rows  = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $items = array_map(fn($r) => [
            'id'         => (int)$r['id'],
            'session_id' => (int)$r['session_id'],
            'type'       => $r['type'],
            'sens'       => $r['sens'],
            'montant'    => round((float)$r['montant'], 2),
            'libelle'    => $r['libelle'],
            'statut'     => $r['statut'],
            'created_at' => $r['created_at'],
        ], $rows);

        $this->respondCollection($items, $total, $paging, '/api/v1/caisse/mouvements');
    }
}

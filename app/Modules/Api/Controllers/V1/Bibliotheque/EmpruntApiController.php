<?php
declare(strict_types=1);

namespace App\Modules\Api\Controllers\V1\Bibliotheque;

use App\Modules\Api\Controllers\ResourceApiController;
use App\Modules\Api\Exceptions\NotFoundException;
use Core\Database;

class EmpruntApiController extends ResourceApiController
{
    /** GET /api/v1/emprunts */
    public function index(): void
    {
        $ctx = $this->requireApiAuth();
        $this->requireApiPermission('biblio.emprunt.view');
        $this->checkRateLimit();

        $paging  = $this->parsePagination();
        $filters = $this->parseFilters(['emprunteur_id', 'statut', 'livre_id']);
        $sort    = $this->parseSort(['date_emprunt', 'date_retour_prevue', 'created_at'], 'date_emprunt');

        $pdo        = Database::getInstance()->getConnection();
        $conditions = ['em.etablissement_id = ?'];
        $bindings   = [$ctx->etablissementId];
        foreach ($filters['conditions'] as $c) { $conditions[] = $c; }
        array_push($bindings, ...$filters['bindings']);

        $where = 'WHERE ' . implode(' AND ', $conditions);
        $cnt   = $pdo->prepare("SELECT COUNT(*) FROM biblio_emprunts em $where");
        $cnt->execute($bindings);
        $total = (int)$cnt->fetchColumn();

        $stmt = $pdo->prepare(
            "SELECT em.*, l.titre AS livre_titre, l.auteur AS livre_auteur,
                    u.nom AS emprunteur_nom
             FROM biblio_emprunts em
             JOIN biblio_exemplaires ex ON ex.id = em.exemplaire_id
             JOIN biblio_livres l ON l.id = ex.livre_id
             JOIN users u ON u.id = em.emprunteur_id
             $where ORDER BY $sort LIMIT {$paging['per_page']} OFFSET {$paging['offset']}"
        );
        $stmt->execute($bindings);
        $rows  = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $items = array_map(fn($r) => [
            'id'                 => (int)$r['id'],
            'emprunteur_id'      => (int)$r['emprunteur_id'],
            'emprunteur_nom'     => $r['emprunteur_nom'],
            'livre_titre'        => $r['livre_titre'],
            'livre_auteur'       => $r['livre_auteur'],
            'date_emprunt'       => $r['date_emprunt'],
            'date_retour_prevue' => $r['date_retour_prevue'],
            'date_retour_reelle' => $r['date_retour_reelle'],
            'statut'             => $r['statut'],
            'en_retard'          => $r['statut'] === 'emprunte' && $r['date_retour_prevue'] < date('Y-m-d'),
            'created_at'         => $r['created_at'],
        ], $rows);

        $this->respondCollection($items, $total, $paging, '/api/v1/emprunts');
    }

    /** GET /api/v1/emprunts/en-retard */
    public function enRetard(): void
    {
        $ctx = $this->requireApiAuth();
        $this->requireApiPermission('biblio.emprunt.view');
        $this->checkRateLimit();

        $paging = $this->parsePagination();
        $pdo    = Database::getInstance()->getConnection();
        $cnt    = $pdo->prepare(
            "SELECT COUNT(*) FROM biblio_emprunts WHERE etablissement_id=:etab AND statut='emprunte' AND date_retour_prevue < CURDATE()"
        );
        $cnt->execute([':etab' => $ctx->etablissementId]);
        $total = (int)$cnt->fetchColumn();

        $stmt = $pdo->prepare(
            "SELECT em.*, l.titre AS livre_titre, u.nom AS emprunteur_nom,
                    DATEDIFF(CURDATE(), em.date_retour_prevue) AS jours_retard
             FROM biblio_emprunts em
             JOIN biblio_exemplaires ex ON ex.id = em.exemplaire_id
             JOIN biblio_livres l ON l.id = ex.livre_id
             JOIN users u ON u.id = em.emprunteur_id
             WHERE em.etablissement_id=:etab AND em.statut='emprunte' AND em.date_retour_prevue < CURDATE()
             ORDER BY jours_retard DESC LIMIT {$paging['per_page']} OFFSET {$paging['offset']}"
        );
        $stmt->execute([':etab' => $ctx->etablissementId]);
        $rows  = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $items = array_map(fn($r) => [
            'id'             => (int)$r['id'],
            'emprunteur_nom' => $r['emprunteur_nom'],
            'livre_titre'    => $r['livre_titre'],
            'jours_retard'   => (int)$r['jours_retard'],
            'date_retour_prevue' => $r['date_retour_prevue'],
        ], $rows);

        $this->respondCollection($items, $total, $paging, '/api/v1/emprunts/en-retard');
    }
}

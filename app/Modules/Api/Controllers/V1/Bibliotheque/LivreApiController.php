<?php
declare(strict_types=1);

namespace App\Modules\Api\Controllers\V1\Bibliotheque;

use App\Modules\Api\Controllers\ResourceApiController;
use App\Modules\Api\Exceptions\NotFoundException;
use Core\Database;

class LivreApiController extends ResourceApiController
{
    /** GET /api/v1/livres */
    public function index(): void
    {
        $ctx = $this->requireApiAuth();
        $this->requireApiPermission('biblio.livre.view');
        $this->checkRateLimit();

        $paging  = $this->parsePagination();
        $filters = $this->parseFilters(['categorie_id', 'disponible', 'langue', 'auteur_id']);
        $sort    = $this->parseSort(['titre', 'auteur', 'annee_publication', 'created_at'], 'titre');

        $pdo        = Database::getInstance()->getConnection();
        $conditions = ['l.etablissement_id = ?'];
        $bindings   = [$ctx->etablissementId];
        foreach ($filters['conditions'] as $c) { $conditions[] = $c; }
        array_push($bindings, ...$filters['bindings']);

        $where = 'WHERE ' . implode(' AND ', $conditions);
        $cnt   = $pdo->prepare("SELECT COUNT(*) FROM biblio_livres l $where");
        $cnt->execute($bindings);
        $total = (int)$cnt->fetchColumn();

        $stmt = $pdo->prepare(
            "SELECT l.*, c.nom AS categorie_nom,
                    (SELECT COUNT(*) FROM biblio_exemplaires ex WHERE ex.livre_id=l.id AND ex.statut='disponible') AS exemplaires_dispos
             FROM biblio_livres l LEFT JOIN biblio_categories c ON c.id=l.categorie_id
             $where ORDER BY $sort LIMIT {$paging['per_page']} OFFSET {$paging['offset']}"
        );
        $stmt->execute($bindings);
        $rows  = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $items = array_map(fn($r) => [
            'id'                 => (int)$r['id'],
            'titre'              => $r['titre'],
            'auteur'             => $r['auteur'],
            'isbn'               => $r['isbn'],
            'categorie'          => $r['categorie_nom'],
            'annee_publication'  => isset($r['annee_publication']) ? (int)$r['annee_publication'] : null,
            'langue'             => $r['langue'],
            'exemplaires_dispos' => (int)$r['exemplaires_dispos'],
            'created_at'         => $r['created_at'],
        ], $rows);

        $this->respondCollection($items, $total, $paging, '/api/v1/livres');
    }

    /** GET /api/v1/livres/{id} */
    public function show(string $id): void
    {
        $ctx = $this->requireApiAuth();
        $this->requireApiPermission('biblio.livre.view');
        $row = $this->findOne((int)$id, $ctx->etablissementId);
        $this->apiSuccess($row);
    }

    private function findOne(int $id, int $etab): array
    {
        $pdo  = Database::getInstance()->getConnection();
        $stmt = $pdo->prepare('SELECT * FROM biblio_livres WHERE id=:id AND etablissement_id=:etab LIMIT 1');
        $stmt->execute([':id' => $id, ':etab' => $etab]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) throw new NotFoundException('Livre');
        return $row;
    }
}

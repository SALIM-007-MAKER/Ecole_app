<?php
declare(strict_types=1);

namespace App\Modules\Api\Controllers\V1\Documents;

use App\Modules\Api\Controllers\ResourceApiController;
use App\Modules\Api\Exceptions\NotFoundException;
use Core\Database;

class DocumentApiController extends ResourceApiController
{
    /** GET /api/v1/documents */
    public function index(): void
    {
        $ctx = $this->requireApiAuth();
        $this->requireApiPermission('documents.view');
        $this->checkRateLimit();

        $paging  = $this->parsePagination();
        $filters = $this->parseFilters(['type', 'module', 'statut', 'owner_id']);
        $sort    = $this->parseSort(['titre', 'created_at', 'taille'], 'created_at');

        $pdo        = Database::getInstance()->getConnection();
        $conditions = ['d.etablissement_id = ?', "d.statut != 'archive'"];
        $bindings   = [$ctx->etablissementId];
        foreach ($filters['conditions'] as $c) { $conditions[] = $c; }
        array_push($bindings, ...$filters['bindings']);

        $where = 'WHERE ' . implode(' AND ', $conditions);
        $cnt   = $pdo->prepare("SELECT COUNT(*) FROM doc_documents d $where");
        $cnt->execute($bindings);
        $total = (int)$cnt->fetchColumn();

        $stmt = $pdo->prepare(
            "SELECT d.id, d.titre, d.type, d.module, d.statut, d.taille, d.mime_type,
                    d.version_courante, d.owner_id, d.created_at
             FROM doc_documents d $where ORDER BY $sort LIMIT {$paging['per_page']} OFFSET {$paging['offset']}"
        );
        $stmt->execute($bindings);
        $rows  = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $items = array_map(fn($r) => [
            'id'              => (int)$r['id'],
            'titre'           => $r['titre'],
            'type'            => $r['type'],
            'module'          => $r['module'],
            'statut'          => $r['statut'],
            'taille'          => (int)$r['taille'],
            'mime_type'       => $r['mime_type'],
            'version_courante' => (int)$r['version_courante'],
            'download_url'    => '/api/v1/documents/' . $r['id'] . '/download',
            'created_at'      => $r['created_at'],
        ], $rows);

        $this->respondCollection($items, $total, $paging, '/api/v1/documents');
    }

    /** GET /api/v1/documents/{id} */
    public function show(string $id): void
    {
        $ctx = $this->requireApiAuth();
        $this->requireApiPermission('documents.view');
        $row = $this->findOne((int)$id, $ctx->etablissementId);
        $this->apiSuccess($row);
    }

    /** GET /api/v1/documents/{id}/download — génère URL signée */
    public function download(string $id): void
    {
        $ctx = $this->requireApiAuth();
        $this->requireApiPermission('documents.download');
        $row = $this->findOne((int)$id, $ctx->etablissementId);

        // URL signée HMAC valable 1h — délègue au StorageService existant
        $expires = time() + 3600;
        $sig     = hash_hmac('sha256', $id . ':' . $expires . ':' . $ctx->userId, (string)getenv('APP_KEY'));
        $this->apiSuccess([
            'document_id'  => (int)$id,
            'download_url' => '/v2/documents/' . $id . '/download?expires=' . $expires . '&sig=' . substr($sig, 0, 16),
            'expires_at'   => date('c', $expires),
        ]);
    }

    private function findOne(int $id, int $etab): array
    {
        $pdo  = Database::getInstance()->getConnection();
        $stmt = $pdo->prepare("SELECT * FROM doc_documents WHERE id=:id AND etablissement_id=:etab AND statut != 'archive' LIMIT 1");
        $stmt->execute([':id' => $id, ':etab' => $etab]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) throw new NotFoundException('Document');
        return $row;
    }
}

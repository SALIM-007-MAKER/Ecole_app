<?php
declare(strict_types=1);

namespace App\Modules\Portals\Shared\Search;

use App\Modules\Portals\Contracts\SearchHandlerInterface;
use Core\Database;

class DocumentSearchHandler implements SearchHandlerInterface
{
    public function getModule(): string { return 'documents'; }

    public function getPortals(): array
    {
        return ['admin', 'direction', 'enseignant', 'eleve', 'parent', 'comptabilite', 'rh'];
    }

    public function getPermissions(): array { return ['document.view']; }

    public function getPriority(): int { return 20; }

    public function search(string $query, int $etablissementId, int $userId, int $limit): array
    {
        try {
            $pdo  = Database::getInstance()->getConnection();
            $like = '%' . $query . '%';
            $stmt = $pdo->prepare(
                "SELECT d.id,
                        d.titre,
                        d.type AS sous_titre,
                        CONCAT('/v2/documents/', d.id) AS url
                 FROM doc_documents d
                 WHERE d.etablissement_id = :etab
                   AND d.deleted_at IS NULL
                   AND (d.titre LIKE :q OR d.description LIKE :q2)
                 ORDER BY d.created_at DESC
                 LIMIT :lim"
            );
            $stmt->bindValue(':etab', $etablissementId, \PDO::PARAM_INT);
            $stmt->bindValue(':q',    $like);
            $stmt->bindValue(':q2',   $like);
            $stmt->bindValue(':lim',  $limit, \PDO::PARAM_INT);
            $stmt->execute();

            return array_map(fn($r) => array_merge($r, [
                'module' => 'documents',
                'type'   => 'document',
                'icon'   => 'file-text',
            ]), $stmt->fetchAll(\PDO::FETCH_ASSOC));
        } catch (\Throwable) {
            return [];
        }
    }
}

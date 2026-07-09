<?php
declare(strict_types=1);

namespace App\Modules\Portals\Shared\Search;

use App\Modules\Portals\Contracts\SearchHandlerInterface;
use Core\Database;

class EleveSearchHandler implements SearchHandlerInterface
{
    public function getModule(): string { return 'scolarite'; }

    public function getPortals(): array { return ['admin', 'direction', 'enseignant', 'comptabilite', 'rh']; }

    public function getPermissions(): array { return ['eleves.view']; }

    public function getPriority(): int { return 10; }

    public function search(string $query, int $etablissementId, int $userId, int $limit): array
    {
        try {
            $pdo   = Database::getInstance()->getConnection();
            $like  = '%' . $query . '%';
            $stmt  = $pdo->prepare(
                "SELECT e.id,
                        CONCAT(e.prenom, ' ', e.nom) AS titre,
                        e.matricule AS sous_titre,
                        CONCAT('/eleves/', e.id) AS url
                 FROM eleves e
                 WHERE e.etablissement_id = :etab
                   AND e.deleted_at IS NULL
                   AND (e.nom LIKE :q OR e.prenom LIKE :q2 OR e.matricule LIKE :q3)
                 ORDER BY e.nom, e.prenom
                 LIMIT :lim"
            );
            $stmt->bindValue(':etab', $etablissementId, \PDO::PARAM_INT);
            $stmt->bindValue(':q',    $like);
            $stmt->bindValue(':q2',   $like);
            $stmt->bindValue(':q3',   $like);
            $stmt->bindValue(':lim',  $limit, \PDO::PARAM_INT);
            $stmt->execute();

            return array_map(fn($r) => array_merge($r, [
                'module' => 'scolarite',
                'type'   => 'eleve',
                'icon'   => 'user',
            ]), $stmt->fetchAll(\PDO::FETCH_ASSOC));
        } catch (\Throwable) {
            return [];
        }
    }
}

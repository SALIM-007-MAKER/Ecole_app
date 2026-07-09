<?php
declare(strict_types=1);

namespace App\Modules\Api\Controllers\V1;

use App\Modules\Api\Controllers\ApiBaseController;
use App\Modules\Api\Exceptions\ValidationException;
use Core\Database;

class SearchController extends ApiBaseController
{
    private const MAX_RESULTS_PER_TYPE = 5;

    /** GET /api/v1/search?q=...&types=eleves,classes,employes */
    public function search(): void
    {
        $ctx = $this->requireApiAuth();
        $this->checkRateLimit();

        $q = trim($_GET['q'] ?? '');
        if (strlen($q) < 2) {
            throw new ValidationException(['q' => 'La recherche doit contenir au moins 2 caractères.']);
        }

        $types = isset($_GET['types'])
            ? array_filter(explode(',', $_GET['types']))
            : ['eleves', 'classes', 'employes', 'documents'];

        $results = [];
        $etab    = $ctx->etablissementId;

        foreach ($types as $type) {
            $type = trim($type);
            switch ($type) {
                case 'eleves':
                    if ($ctx->hasAnyPermission('eleves.view')) {
                        $results['eleves'] = $this->searchEleves($q, $etab);
                    }
                    break;
                case 'classes':
                    if ($ctx->hasAnyPermission('classes.view')) {
                        $results['classes'] = $this->searchClasses($q, $etab);
                    }
                    break;
                case 'employes':
                    if ($ctx->hasAnyPermission('employee.view')) {
                        $results['employes'] = $this->searchEmployes($q, $etab);
                    }
                    break;
                case 'documents':
                    if ($ctx->hasAnyPermission('documents.view')) {
                        $results['documents'] = $this->searchDocuments($q, $etab);
                    }
                    break;
            }
        }

        $this->apiSuccess([
            'query'   => $q,
            'results' => $results,
            'total'   => array_sum(array_map('count', $results)),
        ]);
    }

    // ── Private search helpers ─────────────────────────────────────────────────

    private function searchEleves(string $q, int $etab): array
    {
        $pdo  = Database::getInstance()->getConnection();
        $like = '%' . $q . '%';
        $stmt = $pdo->prepare(
            'SELECT e.id, e.matricule, e.nom, e.prenom, c.nom AS classe_nom
             FROM eleves e LEFT JOIN classes c ON c.id=e.classe_id
             WHERE e.etablissement_id=:etab AND e.deleted_at IS NULL
               AND (e.nom LIKE :q OR e.prenom LIKE :q OR e.matricule LIKE :q)
             LIMIT ' . self::MAX_RESULTS_PER_TYPE
        );
        $stmt->execute([':etab' => $etab, ':q' => $like]);
        return array_map(fn($r) => [
            'type'       => 'eleve',
            'id'         => (int)$r['id'],
            'label'      => $r['nom'] . ' ' . $r['prenom'],
            'subtitle'   => $r['matricule'] . ' — ' . ($r['classe_nom'] ?? ''),
            'url'        => '/api/v1/eleves/' . $r['id'],
        ], $stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    private function searchClasses(string $q, int $etab): array
    {
        $pdo  = Database::getInstance()->getConnection();
        $stmt = $pdo->prepare(
            'SELECT id, nom, niveau FROM classes WHERE etablissement_id=:etab AND deleted_at IS NULL AND nom LIKE :q
             LIMIT ' . self::MAX_RESULTS_PER_TYPE
        );
        $stmt->execute([':etab' => $etab, ':q' => '%' . $q . '%']);
        return array_map(fn($r) => [
            'type'     => 'classe',
            'id'       => (int)$r['id'],
            'label'    => $r['nom'],
            'subtitle' => $r['niveau'],
            'url'      => '/api/v1/classes/' . $r['id'],
        ], $stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    private function searchEmployes(string $q, int $etab): array
    {
        $pdo  = Database::getInstance()->getConnection();
        $like = '%' . $q . '%';
        $stmt = $pdo->prepare(
            'SELECT id, matricule, nom, prenom, poste FROM rh_employees
             WHERE etablissement_id=:etab AND deleted_at IS NULL
               AND (nom LIKE :q OR prenom LIKE :q OR matricule LIKE :q OR email LIKE :q)
             LIMIT ' . self::MAX_RESULTS_PER_TYPE
        );
        $stmt->execute([':etab' => $etab, ':q' => $like]);
        return array_map(fn($r) => [
            'type'     => 'employe',
            'id'       => (int)$r['id'],
            'label'    => $r['nom'] . ' ' . $r['prenom'],
            'subtitle' => $r['matricule'] . ' — ' . ($r['poste'] ?? ''),
            'url'      => '/api/v1/employes/' . $r['id'],
        ], $stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    private function searchDocuments(string $q, int $etab): array
    {
        $pdo  = Database::getInstance()->getConnection();
        $stmt = $pdo->prepare(
            "SELECT id, titre, type FROM doc_documents
             WHERE etablissement_id=:etab AND statut != 'archive' AND titre LIKE :q
             LIMIT " . self::MAX_RESULTS_PER_TYPE
        );
        $stmt->execute([':etab' => $etab, ':q' => '%' . $q . '%']);
        return array_map(fn($r) => [
            'type'     => 'document',
            'id'       => (int)$r['id'],
            'label'    => $r['titre'],
            'subtitle' => $r['type'],
            'url'      => '/api/v1/documents/' . $r['id'],
        ], $stmt->fetchAll(\PDO::FETCH_ASSOC));
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Documents\Services;

use App\Modules\Documents\DTO\SearchDTO;
use Core\Database;
use PDO;

class SearchService
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    // Accepte $user pour compatibilité avec le contrôleur — non utilisé en interne
    public function rechercher(SearchDTO $dto, array $user = []): array
    {
        if (trim($dto->query) === '') {
            return ['data' => [], 'total' => 0, 'page' => $dto->page, 'per_page' => $dto->perPage];
        }

        [$where, $params] = $this->buildWhere($dto);
        $offset = ($dto->page - 1) * $dto->perPage;
        $params[':limit']  = $dto->perPage;
        $params[':offset'] = $offset;
        $params[':q2']     = $dto->query;

        $st = $this->pdo->prepare(
            "SELECT d.*, c.libelle AS categorie_libelle,
                    MATCH(d.titre,d.description,d.reference_externe,d.notes) AGAINST(:q2) AS relevance
             FROM doc_documents d
             LEFT JOIN doc_categories c ON c.id = d.categorie_id
             WHERE d.deleted_at IS NULL $where
             ORDER BY relevance DESC, d.created_at DESC
             LIMIT :limit OFFSET :offset"
        );
        $st->execute($params);
        $data = $st->fetchAll(PDO::FETCH_ASSOC);

        return [
            'data'     => $data,
            'total'    => $this->compter($dto),
            'page'     => $dto->page,
            'per_page' => $dto->perPage,
        ];
    }

    public function compter(SearchDTO $dto): int
    {
        if (trim($dto->query) === '') return 0;
        [$where, $params] = $this->buildWhere($dto);
        $st = $this->pdo->prepare(
            "SELECT COUNT(*) FROM doc_documents d WHERE d.deleted_at IS NULL $where"
        );
        $st->execute($params);
        return (int)$st->fetchColumn();
    }

    // Accepte $user (array) pour compatibilité avec le contrôleur — moduleSource extrait de $_GET
    public function suggestions(string $query, array $user = []): array
    {
        if (trim($query) === '') return [];
        $moduleSource = $_GET['module_source'] ?? '';
        $modClause    = $moduleSource !== '' ? ' AND module_source = :mod' : '';
        $params       = [':q' => '%' . $query . '%', ':limit' => 10];
        if ($moduleSource !== '') $params[':mod'] = $moduleSource;

        $st = $this->pdo->prepare(
            "SELECT id, titre, module_source FROM doc_documents
             WHERE deleted_at IS NULL AND statut = 'actif' AND titre LIKE :q $modClause
             ORDER BY titre ASC LIMIT :limit"
        );
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    private function buildWhere(SearchDTO $dto): array
    {
        $where  = ' AND MATCH(d.titre,d.description,d.reference_externe,d.notes) AGAINST(:q IN BOOLEAN MODE)';
        $params = [':q' => $dto->query . '*'];

        if ($dto->statut !== '') { $where .= ' AND d.statut = :statut'; $params[':statut'] = $dto->statut; }
        if ($dto->moduleSource !== '') { $where .= ' AND d.module_source = :mod'; $params[':mod'] = $dto->moduleSource; }
        if ($dto->excludeSecret) { $where .= " AND d.confidentialite != 'secret'"; }
        if ($dto->etablissementId > 0) { $where .= ' AND d.etablissement_id = :etab'; $params[':etab'] = $dto->etablissementId; }

        return [$where, $params];
    }
}

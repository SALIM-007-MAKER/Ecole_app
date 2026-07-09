<?php
declare(strict_types=1);

namespace App\Modules\Api\Controllers;

use App\Shared\Api\FilterService;
use App\Shared\Api\PaginationService;
use App\Shared\Api\SortService;

/**
 * Contrôleur de base pour les ressources CRUD avec pagination/filtrage/tri.
 */
abstract class ResourceApiController extends ApiBaseController
{
    protected PaginationService $pagination;
    protected FilterService     $filter;
    protected SortService       $sort;

    public function __construct()
    {
        parent::__construct();
        $this->pagination = new PaginationService();
        $this->filter     = new FilterService();
        $this->sort       = new SortService();
    }

    /**
     * Parse les paramètres de pagination depuis la requête.
     */
    protected function parsePagination(): array
    {
        return $this->pagination->parse($this->request);
    }

    /**
     * Parse les paramètres de filtrage (whitelist obligatoire).
     */
    protected function parseFilters(array $allowedFields, string $tableAlias = ''): array
    {
        return $this->filter->parse($this->request, $allowedFields, $tableAlias);
    }

    /**
     * Parse le paramètre de tri (whitelist obligatoire).
     */
    protected function parseSort(array $allowedFields, string $defaultSort = 'created_at', string $tableAlias = ''): string
    {
        return $this->sort->parse($this->request, $allowedFields, $defaultSort, $tableAlias);
    }

    /**
     * Construit une réponse collection paginée.
     */
    protected function respondCollection(
        array  $data,
        int    $total,
        array  $paging,
        string $basePath = ''
    ): never {
        $meta  = $this->pagination->buildMeta($total, $paging['page'], $paging['per_page']);
        $links = $basePath !== ''
            ? $this->pagination->buildLinks($basePath, $total, $paging['page'], $paging['per_page'])
            : [];
        $this->apiCollection($data, $meta, $links);
    }

    /**
     * Vérifie la présence de champs obligatoires dans le body.
     * Lève ValidationException si manquants.
     */
    protected function requireFields(array $body, array $required): void
    {
        $errors = [];
        foreach ($required as $field) {
            if (!isset($body[$field]) || (is_string($body[$field]) && trim($body[$field]) === '')) {
                $errors[$field][] = "Le champ '$field' est obligatoire.";
            }
        }
        if (!empty($errors)) {
            throw new \App\Modules\Api\Exceptions\ValidationException($errors);
        }
    }

    /**
     * Cast et assainit une valeur entière (protection injection).
     */
    protected function intParam(string $key, int $default = 0): int
    {
        return (int)($this->request->get($key) ?? $default);
    }
}

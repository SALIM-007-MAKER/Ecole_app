<?php
declare(strict_types=1);

namespace App\Shared\Api;

use Core\Request;

class PaginationService
{
    private int $defaultPerPage;
    private int $maxPerPage;

    public function __construct()
    {
        $cfg = require ROOT_PATH . '/config/api.php';
        $this->defaultPerPage = $cfg['pagination']['default_per_page'];
        $this->maxPerPage     = $cfg['pagination']['max_per_page'];
    }

    public function parse(Request $request): array
    {
        $page    = max(1, (int)($request->get('page') ?? 1));
        $perPage = min($this->maxPerPage, max(1, (int)($request->get('per_page') ?? $this->defaultPerPage)));

        return [
            'page'     => $page,
            'per_page' => $perPage,
            'offset'   => ($page - 1) * $perPage,
        ];
    }

    public function buildMeta(int $total, int $page, int $perPage): array
    {
        $lastPage = max(1, (int)ceil($total / $perPage));
        return [
            'total'     => $total,
            'page'      => $page,
            'per_page'  => $perPage,
            'last_page' => $lastPage,
            'from'      => $total > 0 ? (($page - 1) * $perPage) + 1 : 0,
            'to'        => min($page * $perPage, $total),
        ];
    }

    public function buildLinks(string $basePath, int $total, int $page, int $perPage, array $extraParams = []): array
    {
        $lastPage = max(1, (int)ceil($total / $perPage));
        $qs       = fn(int $p): string => '?' . http_build_query(array_merge($extraParams, ['page' => $p, 'per_page' => $perPage]));

        return [
            'self'  => $basePath . $qs($page),
            'first' => $basePath . $qs(1),
            'prev'  => $page > 1 ? $basePath . $qs($page - 1) : null,
            'next'  => $page < $lastPage ? $basePath . $qs($page + 1) : null,
            'last'  => $basePath . $qs($lastPage),
        ];
    }
}

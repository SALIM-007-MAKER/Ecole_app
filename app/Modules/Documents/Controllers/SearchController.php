<?php

declare(strict_types=1);

namespace App\Modules\Documents\Controllers;

use Core\Controller;
use App\Modules\Documents\DTO\SearchDTO;
use App\Modules\Documents\Services\SearchService;

class SearchController extends Controller
{
    private SearchService $service;

    public function __construct()
    {
        parent::__construct();
        $this->service = new SearchService();
    }

    public function index(): void
    {
        $this->requirePermission('document.view');

        $dto     = SearchDTO::fromRequest($_GET);
        $results = $this->service->rechercher($dto, $this->user);

        if ($this->isAjax()) {
            $this->json(['results' => $results['data'], 'total' => $results['total']]);
            return;
        }

        $this->render('Documents::search/index', [
            'results'  => $results['data'],
            'total'    => $results['total'],
            'dto'      => $dto,
        ]);
    }

    public function suggestions(): void
    {
        $this->requirePermission('document.view');
        $q = trim($_GET['q'] ?? '');
        if (strlen($q) < 2) { $this->json([]); return; }
        $this->json($this->service->suggestions($q, $this->user));
    }

    private function isAjax(): bool
    {
        return ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest'
            || ($_SERVER['HTTP_ACCEPT'] ?? '') === 'application/json';
    }
}

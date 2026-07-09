<?php

declare(strict_types=1);

namespace App\Modules\Inventaire\Controllers;

use Core\Controller;
use App\Modules\Inventaire\Services\ArticleService;
use App\Modules\Inventaire\Services\AffectationService;
use App\Modules\Inventaire\Services\CategorieService;
use App\Modules\Inventaire\Services\FournisseurService;
use App\Modules\Inventaire\Services\StockService;
use App\Modules\Inventaire\DTO\ArticleDTO;
use App\Modules\Inventaire\DTO\ArticleFiltersDTO;

class ArticleController extends Controller
{
    private ArticleService     $service;
    private AffectationService $affectations;
    private CategorieService   $categories;
    private FournisseurService $fournisseurs;
    private StockService       $stocks;

    public function __construct()
    {
        $this->service      = new ArticleService();
        $this->affectations = new AffectationService();
        $this->categories   = new CategorieService();
        $this->fournisseurs = new FournisseurService();
        $this->stocks       = new StockService();
    }

    public function index(): void
    {
        $this->requirePermission('inventaire.view');
        $etab    = (int)($this->user['etablissement_id'] ?? 1);
        $filters = ArticleFiltersDTO::fromRequest($_GET);
        $data    = $this->service->lister($filters, $etab);

        $this->render('Inventaire::articles/index', [
            'articles'   => $data['items'],
            'pagination' => $data,
            'categories' => $this->categories->lister($etab),
            'filters'    => $filters,
        ]);
    }

    public function show(int $id): void
    {
        $this->requirePermission('inventaire.view');
        $article = $this->service->trouver($id);
        if (!$article) { $this->redirect('/v2/inventaire/articles'); return; }
        $etab = (int)($this->user['etablissement_id'] ?? 1);

        $this->render('Inventaire::articles/show', [
            'article'      => $article,
            'stocks'       => $this->stocks->getByArticle($id),
            'affectations' => $this->affectations->parArticle($id),
            'mouvements'   => $this->stocks->mouvements($id),
        ]);
    }

    public function create(): void
    {
        $this->requirePermission('inventaire.create');
        $etab = (int)($this->user['etablissement_id'] ?? 1);
        $this->render('Inventaire::articles/form', [
            'article'      => null,
            'categories'   => $this->categories->lister($etab),
            'fournisseurs' => $this->fournisseurs->lister($etab, 'actif'),
        ]);
    }

    public function store(): void
    {
        $this->requirePermission('inventaire.create');
        $this->verifyCsrf();
        $etab = (int)($this->user['etablissement_id'] ?? 1);

        try {
            $dto = ArticleDTO::fromRequest($_POST);
            $id  = $this->service->creer($dto, (int)$this->user['id'], $etab);
            $this->redirect("/v2/inventaire/articles/{$id}");
        } catch (\RuntimeException $e) {
            $this->render('Inventaire::articles/form', [
                'error'        => $e->getMessage(),
                'article'      => null,
                'categories'   => $this->categories->lister($etab),
                'fournisseurs' => $this->fournisseurs->lister($etab, 'actif'),
            ]);
        }
    }

    public function edit(int $id): void
    {
        $this->requirePermission('inventaire.edit');
        $article = $this->service->trouver($id);
        if (!$article) { $this->redirect('/v2/inventaire/articles'); return; }
        $etab = (int)($this->user['etablissement_id'] ?? 1);

        $this->render('Inventaire::articles/form', [
            'article'      => $article,
            'categories'   => $this->categories->lister($etab),
            'fournisseurs' => $this->fournisseurs->lister($etab, 'actif'),
        ]);
    }

    public function update(int $id): void
    {
        $this->requirePermission('inventaire.edit');
        $this->verifyCsrf();
        $etab = (int)($this->user['etablissement_id'] ?? 1);

        try {
            $dto = ArticleDTO::fromRequest($_POST);
            $this->service->modifier($id, $dto, (int)$this->user['id']);
            $this->redirect("/v2/inventaire/articles/{$id}");
        } catch (\RuntimeException $e) {
            $article = $this->service->trouver($id);
            $this->render('Inventaire::articles/form', [
                'error'        => $e->getMessage(),
                'article'      => $article,
                'categories'   => $this->categories->lister($etab),
                'fournisseurs' => $this->fournisseurs->lister($etab, 'actif'),
            ]);
        }
    }

    public function destroy(int $id): void
    {
        $this->requirePermission('inventaire.delete');
        $this->verifyCsrf();
        try {
            $this->service->archiver($id, (int)$this->user['id']);
        } catch (\RuntimeException $e) {
            // silently redirect
        }
        $this->redirect('/v2/inventaire/articles');
    }

    public function scanBarcode(): void
    {
        $this->requirePermission('inventaire.view');
        $etab    = (int)($this->user['etablissement_id'] ?? 1);
        $barcode = $_GET['code'] ?? '';
        $article = $this->service->trouverParBarcode($barcode, $etab);
        $this->json(['found' => (bool)$article, 'article' => $article]);
    }
}

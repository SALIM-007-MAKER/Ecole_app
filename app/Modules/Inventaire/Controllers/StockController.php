<?php

declare(strict_types=1);

namespace App\Modules\Inventaire\Controllers;

use Core\Controller;
use App\Modules\Inventaire\Services\StockService;
use App\Modules\Inventaire\Services\ArticleService;
use App\Modules\Inventaire\Services\EmplacementService;
use App\Modules\Inventaire\DTO\ArticleFiltersDTO;

class StockController extends Controller
{
    private StockService      $service;
    private ArticleService    $articles;
    private EmplacementService $emplacements;

    public function __construct()
    {
        $this->service      = new StockService();
        $this->articles     = new ArticleService();
        $this->emplacements = new EmplacementService();
    }

    public function index(): void
    {
        $this->requirePermission('inventaire.stock.view');
        $etab = (int)($this->user['etablissement_id'] ?? 1);
        $this->render('Inventaire::stocks/index', [
            'stocks'      => $this->service->etatGlobal($etab),
            'alertes'     => $this->articles->articlesEnAlerte($etab),
            'emplacements'=> $this->emplacements->lister($etab),
        ]);
    }

    public function mouvements(): void
    {
        $this->requirePermission('inventaire.stock.view');
        $etab = (int)($this->user['etablissement_id'] ?? 1);
        $this->render('Inventaire::stocks/mouvements', [
            'mouvements' => $this->service->derniersMovements($etab, 100),
        ]);
    }

    public function ajuster(): void
    {
        $this->requirePermission('inventaire.stock.manage');
        $this->verifyCsrf();
        $etab = (int)($this->user['etablissement_id'] ?? 1);
        try {
            $this->service->ajuster(
                (int)($_POST['article_id'] ?? 0),
                (int)($_POST['emplacement_id'] ?? 1),
                (float)($_POST['nouvelle_quantite'] ?? 0),
                $_POST['motif'] ?? 'Ajustement manuel',
                (int)$this->user['id'],
                $etab,
            );
        } catch (\RuntimeException $e) {}
        $this->redirect('/v2/inventaire/stocks');
    }

    public function transferer(): void
    {
        $this->requirePermission('inventaire.stock.manage');
        $this->verifyCsrf();
        $etab = (int)($this->user['etablissement_id'] ?? 1);
        try {
            $this->service->transferer(
                (int)($_POST['article_id'] ?? 0),
                (float)($_POST['quantite'] ?? 0),
                (int)($_POST['source_id'] ?? 0),
                (int)($_POST['destination_id'] ?? 0),
                (int)$this->user['id'],
                $etab,
            );
        } catch (\RuntimeException $e) {}
        $this->redirect('/v2/inventaire/stocks');
    }
}

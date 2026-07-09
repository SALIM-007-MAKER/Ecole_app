<?php

declare(strict_types=1);

namespace App\Modules\Inventaire\Controllers;

use Core\Controller;
use App\Modules\Inventaire\Services\ArticleService;
use App\Modules\Inventaire\Services\StockService;
use App\Modules\Inventaire\Services\AlerteService;
use App\Modules\Inventaire\Services\AffectationService;
use App\Modules\Inventaire\Services\MaintenanceService;
use App\Modules\Inventaire\Services\AmortissementService;
use App\Modules\Inventaire\Repositories\ArticleRepository;
use App\Modules\Inventaire\Repositories\MouvementRepository;

class InventaireAnalyticsController extends Controller
{
    private ArticleService      $articles;
    private StockService        $stocks;
    private AlerteService       $alertes;
    private AffectationService  $affectations;
    private MaintenanceService  $maintenances;
    private AmortissementService $amortissements;
    private MouvementRepository  $mouvements;
    private ArticleRepository    $artRepo;

    public function __construct()
    {
        $this->articles       = new ArticleService();
        $this->stocks         = new StockService();
        $this->alertes        = new AlerteService();
        $this->affectations   = new AffectationService();
        $this->maintenances   = new MaintenanceService();
        $this->amortissements = new AmortissementService();
        $this->mouvements     = new MouvementRepository();
        $this->artRepo        = new ArticleRepository();
    }

    public function dashboard(): void
    {
        $this->requirePermission('inventaire.reports');
        $etab = (int)($this->user['etablissement_id'] ?? 1);
        $from = $_GET['from'] ?? date('Y-m-01');
        $to   = $_GET['to']   ?? date('Y-m-t');

        $this->render('Inventaire::analytics/dashboard', [
            'nb_articles'         => count($this->artRepo->search([], $etab)),
            'nb_alertes_actives'  => $this->alertes->countActives($etab),
            'nb_affectations_en_cours' => count($this->affectations->lister($etab, 'en_cours')),
            'nb_maintenances_dues'=> count($this->maintenances->duesThisMonth($etab)),
            'valeur_stock'        => array_sum(array_column($this->stocks->etatGlobal($etab), 'stock_total')),
            'valeur_nette'        => $this->amortissements->totalValeurNette($etab),
            'mouvements_stats'    => $this->mouvements->statsByType($etab, $from, $to),
            'articles_alerte'     => $this->articles->articlesEnAlerte($etab),
            'from'                => $from,
            'to'                  => $to,
        ]);
    }
}

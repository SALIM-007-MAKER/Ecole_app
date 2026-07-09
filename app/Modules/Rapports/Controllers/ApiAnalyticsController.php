<?php
declare(strict_types=1);

namespace App\Modules\Rapports\Controllers;

use Core\Controller;
use App\Modules\Rapports\Services\DataAggregatorService;
use App\Modules\Rapports\Services\KpiService;
use App\Modules\Rapports\Services\TendanceService;
use App\Modules\Rapports\Services\PredictionService;

class ApiAnalyticsController extends Controller
{
    private DataAggregatorService $aggregator;
    private KpiService            $kpiService;
    private TendanceService       $tendance;
    private PredictionService     $prediction;

    public function __construct()
    {
        $this->aggregator = new DataAggregatorService();
        $this->kpiService = new KpiService();
        $this->tendance   = new TendanceService();
        $this->prediction = new PredictionService();
    }

    public function kpis(): void
    {
        $this->requirePermission('rapports.api.analytics');
        $etab    = (int)($this->user['etablissement_id'] ?? 1);
        $domaine = $_GET['domaine'] ?? 'scolarite';
        $this->json($this->kpiService->getKpisDomaine($domaine, $etab));
    }

    public function domaine(): void
    {
        $this->requirePermission('rapports.api.analytics');
        $etab    = (int)($this->user['etablissement_id'] ?? 1);
        $domaine = $_GET['domaine'] ?? 'scolarite';
        $filters = $_GET;
        $this->json($this->aggregator->getForDomaine($domaine, $etab, $filters));
    }

    public function tendanceJson(): void
    {
        $this->requirePermission('rapports.api.analytics');
        $etab     = (int)($this->user['etablissement_id'] ?? 1);
        $domaine  = $_GET['domaine']  ?? 'scolarite';
        $metrique = $_GET['metrique'] ?? 'nb_eleves';
        $nb       = max(3, min(24, (int)($_GET['nb_periodes'] ?? 12)));
        $this->json($this->tendance->analyserMetrique($domaine, $metrique, $etab, $nb));
    }

    public function alertesJson(): void
    {
        $this->requirePermission('rapports.api.analytics');
        $etab    = (int)($this->user['etablissement_id'] ?? 1);
        $domaine = $_GET['domaine'] ?? 'finance';
        $this->json($this->tendance->alertesKpi($domaine, $etab));
    }

    public function prevision(): void
    {
        $this->requirePermission('rapports.api.analytics');
        $etab   = (int)($this->user['etablissement_id'] ?? 1);
        $type   = $_GET['type'] ?? 'effectifs';
        $result = match ($type) {
            'effectifs' => $this->prediction->previsionEffectifs($etab, $_GET['annee'] ?? date('Y') . '-' . (date('Y') + 1)),
            'budget'    => $this->prediction->previsionBudget($etab, $_GET['mois'] ?? date('Y-m')),
            default     => ['erreur' => 'Type inconnu'],
        };
        $this->json($result);
    }
}

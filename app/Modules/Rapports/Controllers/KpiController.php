<?php
declare(strict_types=1);

namespace App\Modules\Rapports\Controllers;

use Core\Controller;
use App\Modules\Rapports\Services\KpiService;
use App\Modules\Rapports\Services\TendanceService;
use App\Modules\Rapports\Services\SnapshotService;
use App\Modules\Rapports\DTO\SnapshotDTO;

class KpiController extends Controller
{
    private KpiService      $kpiService;
    private TendanceService $tendance;
    private SnapshotService $snapshots;

    public function __construct()
    {
        $this->kpiService = new KpiService();
        $this->tendance   = new TendanceService();
        $this->snapshots  = new SnapshotService();
    }

    public function index(): void
    {
        $this->requirePermission('rapports.kpis.voir');
        $etab    = (int)($this->user['etablissement_id'] ?? 1);
        $domaine = $_GET['domaine'] ?? 'scolarite';
        $kpis    = $this->kpiService->getKpisDomaine($domaine, $etab);
        $alertes = $this->tendance->alertesKpi($domaine, $etab);
        $this->render('Rapports::kpis/index', [
            'kpis'    => $kpis,
            'alertes' => $alertes,
            'domaine' => $domaine,
        ]);
    }

    public function tendanceView(): void
    {
        $this->requirePermission('rapports.kpis.voir');
        $etab    = (int)($this->user['etablissement_id'] ?? 1);
        $domaine = $_GET['domaine']  ?? 'scolarite';
        $metrique = $_GET['metrique'] ?? 'nb_eleves';
        $analyse  = $this->tendance->analyserMetrique($domaine, $metrique, $etab);
        $this->render('Rapports::kpis/tendance', ['analyse' => $analyse, 'domaine' => $domaine, 'metrique' => $metrique]);
    }

    public function snapshot(): void
    {
        $this->requirePermission('rapports.kpis.voir');
        $etab = (int)($this->user['etablissement_id'] ?? 1);
        $nb   = $this->snapshots->capturerTousDomaines($etab);
        $this->json(['success' => true, 'snapshots_captures' => $nb]);
    }

    public function historiqueJson(): void
    {
        $this->requirePermission('rapports.kpis.voir');
        $etab     = (int)($this->user['etablissement_id'] ?? 1);
        $domaine  = $_GET['domaine']  ?? 'scolarite';
        $metrique = $_GET['metrique'] ?? 'nb_eleves';
        $data     = $this->snapshots->historique($domaine, $metrique, $etab);
        $this->json($data);
    }

    public function alertes(): void
    {
        $this->requirePermission('rapports.kpis.voir');
        $etab    = (int)($this->user['etablissement_id'] ?? 1);
        $domaine = $_GET['domaine'] ?? 'finance';
        $alertes = $this->tendance->alertesKpi($domaine, $etab);
        $this->render('Rapports::kpis/alertes', ['alertes' => $alertes, 'domaine' => $domaine]);
    }
}

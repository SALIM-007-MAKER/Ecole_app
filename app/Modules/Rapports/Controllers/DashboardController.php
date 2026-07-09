<?php
declare(strict_types=1);

namespace App\Modules\Rapports\Controllers;

use Core\Controller;
use App\Modules\Rapports\Policies\RapportPolicy;
use App\Modules\Rapports\Services\DashboardService;
use App\Modules\Rapports\Repositories\SnapshotRepository;

class DashboardController extends Controller
{
    private DashboardService   $service;
    private RapportPolicy      $policy;
    private SnapshotRepository $snapshots;

    public function __construct()
    {
        $this->service   = new DashboardService();
        $this->policy    = new RapportPolicy();
        $this->snapshots = new SnapshotRepository();
    }

    public function direction(): void
    {
        $this->requirePermission('rapports.dashboard.direction');
        $etab     = (int)($this->user['etablissement_id'] ?? 1);
        $userId   = (int)($this->user['id']               ?? 0);
        $config   = $_SESSION['bi_config_direction'] ?? [];
        $metrics  = $this->service->getDashboard('direction', $etab, $userId, $config);
        $this->render('Rapports::dashboards/direction', ['metrics' => $metrics]);
    }

    public function administration(): void
    {
        $this->requirePermission('rapports.dashboard.administration');
        $etab    = (int)($this->user['etablissement_id'] ?? 1);
        $userId  = (int)($this->user['id']               ?? 0);
        $metrics = $this->service->getDashboard('administration', $etab, $userId);
        $this->render('Rapports::dashboards/administration', ['metrics' => $metrics]);
    }

    public function scolarite(): void
    {
        $this->requirePermission('rapports.dashboard.scolarite');
        $etab    = (int)($this->user['etablissement_id'] ?? 1);
        $userId  = (int)($this->user['id']               ?? 0);
        $metrics = $this->service->getDashboard('scolarite', $etab, $userId);
        $this->render('Rapports::dashboards/scolarite', ['metrics' => $metrics]);
    }

    public function academique(): void
    {
        $this->requirePermission('rapports.dashboard.academique');
        $etab    = (int)($this->user['etablissement_id'] ?? 1);
        $userId  = (int)($this->user['id']               ?? 0);
        $metrics = $this->service->getDashboard('academique', $etab, $userId);
        $this->render('Rapports::dashboards/academique', ['metrics' => $metrics]);
    }

    public function finance(): void
    {
        $this->requirePermission('rapports.dashboard.finance');
        $etab    = (int)($this->user['etablissement_id'] ?? 1);
        $userId  = (int)($this->user['id']               ?? 0);
        $metrics = $this->service->getDashboard('finance', $etab, $userId);
        $this->render('Rapports::dashboards/finance', ['metrics' => $metrics]);
    }

    public function rh(): void
    {
        $this->requirePermission('rapports.dashboard.rh');
        $etab    = (int)($this->user['etablissement_id'] ?? 1);
        $userId  = (int)($this->user['id']               ?? 0);
        $metrics = $this->service->getDashboard('rh', $etab, $userId);
        $this->render('Rapports::dashboards/rh', ['metrics' => $metrics]);
    }

    public function vieScolaire(): void
    {
        $this->requirePermission('rapports.dashboard.vie_scolaire');
        $etab    = (int)($this->user['etablissement_id'] ?? 1);
        $userId  = (int)($this->user['id']               ?? 0);
        $metrics = $this->service->getDashboard('vie_scolaire', $etab, $userId);
        $this->render('Rapports::dashboards/vie-scolaire', ['metrics' => $metrics]);
    }

    public function bibliotheque(): void
    {
        $this->requirePermission('rapports.dashboard.bibliotheque');
        $etab    = (int)($this->user['etablissement_id'] ?? 1);
        $userId  = (int)($this->user['id']               ?? 0);
        $metrics = $this->service->getDashboard('bibliotheque', $etab, $userId);
        $this->render('Rapports::dashboards/bibliotheque', ['metrics' => $metrics]);
    }

    public function inventaire(): void
    {
        $this->requirePermission('rapports.dashboard.inventaire');
        $etab    = (int)($this->user['etablissement_id'] ?? 1);
        $userId  = (int)($this->user['id']               ?? 0);
        $metrics = $this->service->getDashboard('inventaire', $etab, $userId);
        $this->render('Rapports::dashboards/inventaire', ['metrics' => $metrics]);
    }
}

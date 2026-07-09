<?php

declare(strict_types=1);

namespace App\Modules\RH\Organisation\Controllers;

use App\Modules\RH\Organisation\Policies\OrganizationPolicy;
use App\Modules\RH\Organisation\Services\OrganizationService;
use App\Modules\RH\Organisation\Services\DepartmentService;
use App\Modules\RH\Organisation\Services\PositionService;
use Core\Controller;
use Core\Session;

class OrganizationController extends Controller
{
    private OrganizationService $orgService;
    private DepartmentService   $deptService;
    private PositionService     $posService;
    private OrganizationPolicy  $policy;

    public function __construct()
    {
        parent::__construct();
        $this->orgService  = new OrganizationService();
        $this->deptService = new DepartmentService();
        $this->posService  = new PositionService();
        $this->policy      = new OrganizationPolicy();
    }

    // ── Tableau de bord / Organigramme ────────────────────────────────────────

    public function index(): void
    {
        $this->requirePermission('organization.view');

        $tree  = $this->orgService->organigramme();
        $stats = $this->orgService->statistiques();

        $this->render('RH::organisation/index', [
            'tree'      => $tree,
            'stats'     => $stats,
            'canCreate' => $this->policy->canCreate($this->user),
            'canExport' => $this->policy->canExport($this->user),
        ]);
    }

    // ── Statistiques globales ─────────────────────────────────────────────────

    public function statistiques(): void
    {
        $this->requirePermission('organization.view');

        $stats     = $this->orgService->statistiques();
        $fonctions = $this->posService->findAllFonctions();

        $this->render('RH::organisation/statistiques', [
            'stats'     => $stats,
            'fonctions' => $fonctions,
        ]);
    }

    // ── Export ────────────────────────────────────────────────────────────────

    public function exportDepartements(): void
    {
        $this->requirePermission('organization.export');

        $csv = $this->orgService->exportDepartements();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="departements_' . date('Ymd') . '.csv"');
        echo $csv;
        exit;
    }

    public function exportPostes(): void
    {
        $this->requirePermission('organization.export');

        $csv = $this->orgService->exportPostes();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="postes_' . date('Ymd') . '.csv"');
        echo $csv;
        exit;
    }
}

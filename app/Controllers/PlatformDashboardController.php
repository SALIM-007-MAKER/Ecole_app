<?php

namespace App\Controllers;

use Core\Platform\PlatformController;
use Core\Platform\PlatformStatsService;

class PlatformDashboardController extends PlatformController
{
    private PlatformStatsService $stats;

    public function __construct()
    {
        parent::__construct();
        $this->stats = PlatformStatsService::make();
    }

    public function index(): void
    {
        $this->requirePlatformAuth();

        $this->render('platform/dashboard', [
            'title'          => 'Tableau de bord',
            'kpis'           => $this->stats->globalKpis(),
            'queueStats'     => $this->stats->queueStats(),
            'cacheStats'     => $this->stats->cacheStats(),
            'moduleHealth'   => $this->stats->moduleHealth(),
            'tenantsAtRisk'  => $this->stats->tenantsNearQuota(),
        ], 'platform');
    }

    public function api(): void
    {
        $this->requirePlatformAuth();

        $this->json([
            'kpis'          => $this->stats->globalKpis(),
            'queue'         => $this->stats->queueStats(),
            'cache'         => $this->stats->cacheStats(),
            'modules'       => $this->stats->moduleHealth(),
            'tenants_at_risk' => $this->stats->tenantsNearQuota(),
        ]);
    }

    public function snapshot(): void
    {
        $this->requirePlatformLevel('admin', 'super_admin');
        $this->verifyCsrf();

        $this->stats->recordSnapshot('manual');
        \Core\Session::flash('success', 'Instantané enregistré.');
        $this->redirect(BASE_URL . '/platform/dashboard');
    }
}

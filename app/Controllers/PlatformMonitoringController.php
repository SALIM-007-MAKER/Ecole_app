<?php

namespace App\Controllers;

use Core\Platform\PlatformController;
use Core\Platform\PlatformEtablissementService;
use Core\Queue\JobQueue;
use Core\Session;
use Core\Tenant\TenantCache;

/**
 * Supervision du cache et de la file d'attente — Administration de la
 * plateforme (T026). Anciennement `MonitoringController` (self-service
 * établissement) ; déplacé ici — voir SAAS_CONFIGURATION_METIER_REPORT.md.
 */
class PlatformMonitoringController extends PlatformController
{
    private TenantCache $cache;
    private JobQueue $queue;
    private PlatformEtablissementService $etabs;

    public function __construct()
    {
        parent::__construct();
        $this->cache = TenantCache::make();
        $this->queue = JobQueue::make();
        $this->etabs = PlatformEtablissementService::make();
    }

    public function index(int $id): void
    {
        $this->requirePlatformAuth();

        $etab = $this->etabs->find($id);
        if ($etab === null) {
            http_response_code(404);
            $this->render('errors/404', [], 'none');
            return;
        }

        $this->render('platform/etablissements/monitoring', [
            'title'      => 'Monitoring — ' . $etab['nom'],
            'etab'       => $etab,
            'cacheStats' => [
                'branding'  => $this->cache->stats($id, 'branding'),
                'rbac'      => $this->cache->stats($id, 'rbac'),
                'dashboard' => $this->cache->stats($id, 'dashboard'),
                'total'     => $this->cache->stats($id),
            ],
            'queueStats' => $this->queue->stats($id),
            'recentJobs' => $this->queue->recent($id, 20),
        ], 'platform');
    }

    public function api(int $id): void
    {
        $this->requirePlatformAuth();

        $this->json([
            'cache' => [
                'branding'  => $this->cache->stats($id, 'branding'),
                'rbac'      => $this->cache->stats($id, 'rbac'),
                'dashboard' => $this->cache->stats($id, 'dashboard'),
                'total'     => $this->cache->stats($id),
            ],
            'queue' => $this->queue->stats($id),
            'jobs'  => $this->queue->recent($id, 20),
        ]);
    }

    public function flushCache(int $id): void
    {
        $this->requirePlatformLevel('admin', 'super_admin');
        $this->verifyCsrf();

        $this->cache->flushTenant($id);

        \Core\Logger::security('CACHE_FLUSHED', "etablissement_id={$id} par opérateur plateforme");
        Session::flash('success', 'Cache vidé.');
        $this->redirect(BASE_URL . '/platform/etablissements/' . $id . '/monitoring');
    }
}

<?php

namespace App\Controllers;

use Core\Controller;
use Core\Queue\JobQueue;
use Core\Session;
use Core\Tenant\TenantCache;

/**
 * Supervision du cache et de la file d'attente par établissement
 * (Phase 14.9). Lecture seule. Toujours scopé à l'établissement de
 * l'utilisateur connecté, jamais un identifiant fourni par la requête.
 */
class MonitoringController extends Controller
{
    private TenantCache $cache;
    private JobQueue $queue;

    public function __construct()
    {
        parent::__construct();
        $this->cache = TenantCache::make();
        $this->queue = JobQueue::make();
    }

    private function currentEtablissementId(): int
    {
        $user = Session::getUser();
        $etabId = $user['etablissement_id'] ?? null;
        if ($etabId === null) {
            $config = require ROOT_PATH . '/config/tenant.php';
            return (int)($config['default_id'] ?? 1);
        }
        return (int)$etabId;
    }

    public function index(): void
    {
        $this->requirePermission('monitoring.view');

        $etabId = $this->currentEtablissementId();

        $this->render('settings/monitoring', [
            'title'       => 'Cache & Files d\'attente',
            'cacheStats'  => [
                'branding'  => $this->cache->stats($etabId, 'branding'),
                'rbac'      => $this->cache->stats($etabId, 'rbac'),
                'dashboard' => $this->cache->stats($etabId, 'dashboard'),
                'total'     => $this->cache->stats($etabId),
            ],
            'queueStats'  => $this->queue->stats($etabId),
            'recentJobs'  => $this->queue->recent($etabId, 20),
        ]);
    }

    public function api(): void
    {
        $this->requirePermission('monitoring.view');

        $etabId = $this->currentEtablissementId();

        $this->json([
            'cache' => [
                'branding'  => $this->cache->stats($etabId, 'branding'),
                'rbac'      => $this->cache->stats($etabId, 'rbac'),
                'dashboard' => $this->cache->stats($etabId, 'dashboard'),
                'total'     => $this->cache->stats($etabId),
            ],
            'queue' => $this->queue->stats($etabId),
            'jobs'  => $this->queue->recent($etabId, 20),
        ]);
    }

    public function flushCache(): void
    {
        $this->requirePermission('monitoring.view');
        $this->verifyCsrf();

        $etabId = $this->currentEtablissementId();
        $this->cache->flushTenant($etabId);

        \Core\Logger::security('CACHE_FLUSHED', "etablissement_id={$etabId} par user_id=" . (Session::getUser()['id'] ?? '?'));
        Session::flash('success', 'Cache vidé pour votre établissement.');
        $this->redirect(BASE_URL . '/parametres/monitoring');
    }
}

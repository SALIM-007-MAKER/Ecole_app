<?php

declare(strict_types=1);

namespace App\Modules\Communication\Controllers;

use App\Modules\Communication\Repositories\LogRepository;
use App\Modules\Communication\Repositories\QueueRepository;
use App\Modules\Communication\Services\NotificationService;
use App\Modules\Communication\Services\QueueService;
use Core\Controller;

class AdminController extends Controller
{
    private QueueService     $queueService;
    private QueueRepository  $queueRepo;
    private LogRepository    $logRepo;

    public function __construct()
    {
        parent::__construct();
        $this->queueService = new QueueService();
        $this->queueRepo    = new QueueRepository();
        $this->logRepo      = new LogRepository();
    }

    public function dashboard(): void
    {
        $this->requirePermission('communication.admin');
        $etab        = (int) ($this->user['etablissement_id'] ?? 1);
        $queueStats  = $this->queueService->statistiques($etab);
        $failedJobs  = $this->queueRepo->findFailed(20, $etab);
        $recentLogs  = $this->logRepo->findForReport($etab, 1, 30);
        $channelStats = $this->logRepo->statsByCanal($etab);

        $this->render('Communication::admin/dashboard', [
            'queueStats'   => $queueStats,
            'failedJobs'   => $failedJobs,
            'recentLogs'   => $recentLogs,
            'channelStats' => $channelStats,
            'titre'        => 'Administration Communication',
        ]);
    }

    public function processQueue(): void
    {
        $this->requirePermission('communication.admin');
        $this->verifyCsrf();
        $result = $this->queueService->traiterBatch(50);
        $this->json(array_merge(['success' => true], $result));
    }

    public function retryJob(int $id): void
    {
        $this->requirePermission('communication.admin');
        $this->verifyCsrf();
        $this->queueRepo->resetForRetry($id);
        $this->json(['success' => true]);
    }

    public function retryFailed(): void
    {
        $this->requirePermission('communication.admin');
        $this->verifyCsrf();
        $etab  = (int) ($this->user['etablissement_id'] ?? 1);
        $count = $this->queueRepo->resetAllFailed($etab);
        $this->json(['success' => true, 'reset' => $count]);
    }

    public function logs(): void
    {
        $this->requirePermission('communication.admin');
        $etab = (int) ($this->user['etablissement_id'] ?? 1);
        $page = (int) ($_GET['page'] ?? 1);
        $logs = $this->logRepo->findForReport($etab, $page);
        $this->json(['logs' => $logs, 'page' => $page]);
    }
}

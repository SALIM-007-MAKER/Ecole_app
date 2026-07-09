<?php

declare(strict_types=1);

namespace App\Modules\Documents\Controllers;

use Core\Controller;
use App\Modules\Documents\Policies\DocumentPolicy;
use App\Modules\Documents\Services\DocumentService;
use App\Modules\Documents\Services\QuotaService;

class AdminController extends Controller
{
    private DocumentService $service;
    private QuotaService    $quotas;
    private DocumentPolicy  $policy;

    public function __construct()
    {
        parent::__construct();
        $this->service = new DocumentService();
        $this->quotas  = new QuotaService();
        $this->policy  = new DocumentPolicy();
    }

    public function quotas(): void
    {
        $this->requirePermission('document.admin');

        $this->render('Documents::admin/quotas', [
            'quotas' => $this->quotas->tableau(),
        ]);
    }

    public function updateQuota(string $moduleSource): void
    {
        $this->requirePermission('document.admin');
        $this->verifyCsrf();

        $max = (int)($_POST['max_octets'] ?? 0);
        if ($max < 0) {
            $this->json(['success' => false, 'message' => 'Quota invalide.'], 422);
            return;
        }
        $this->quotas->definir($moduleSource, $max);
        $this->json(['success' => true]);
    }

    public function expirations(): void
    {
        $this->requirePermission('document.admin');

        $this->render('Documents::admin/expirations', [
            'expiring' => $this->service->expirationProchaine(30),
            'expired'  => $this->service->documentsExpires(),
        ]);
    }

    public function runExpireCheck(): void
    {
        $this->requirePermission('document.admin');
        $this->verifyCsrf();

        $count = $this->service->verifierExpirations();
        $this->json(['success' => true, 'processed' => $count]);
    }

    public function statistics(): void
    {
        $this->requirePermission('document.admin');

        $this->render('Documents::admin/statistics', [
            'stats' => $this->service->statistiques(),
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Inventaire\Controllers;

use Core\Controller;
use App\Modules\Inventaire\Services\AlerteService;

class AlerteController extends Controller
{
    private AlerteService $service;

    public function __construct()
    {
        $this->service = new AlerteService();
    }

    public function index(): void
    {
        $this->requirePermission('inventaire.view');
        $etab = (int)($this->user['etablissement_id'] ?? 1);
        $this->render('Inventaire::alertes/index', [
            'alertes' => $this->service->listerToutes($etab),
            'count'   => $this->service->countActives($etab),
        ]);
    }

    public function acquitter(int $id): void
    {
        $this->requirePermission('inventaire.edit');
        $this->verifyCsrf();
        try { $this->service->acquitter($id, (int)$this->user['id']); } catch (\RuntimeException $e) {}
        $this->redirect('/v2/inventaire/alertes');
    }

    public function scanner(): void
    {
        $this->requirePermission('inventaire.stock.manage');
        $etab = (int)($this->user['etablissement_id'] ?? 1);
        $nb   = $this->service->scanner($etab);
        $this->json(['created' => $nb]);
    }
}

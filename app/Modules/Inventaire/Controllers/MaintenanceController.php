<?php

declare(strict_types=1);

namespace App\Modules\Inventaire\Controllers;

use Core\Controller;
use App\Modules\Inventaire\Services\MaintenanceService;
use App\Modules\Inventaire\Services\ArticleService;
use App\Modules\Inventaire\DTO\MaintenanceDTO;
use App\Modules\Inventaire\DTO\ArticleFiltersDTO;

class MaintenanceController extends Controller
{
    private MaintenanceService $service;
    private ArticleService     $articles;

    public function __construct()
    {
        $this->service  = new MaintenanceService();
        $this->articles = new ArticleService();
    }

    public function index(): void
    {
        $this->requirePermission('inventaire.maintenance.view');
        $etab    = (int)($this->user['etablissement_id'] ?? 1);
        $filters = ['statut' => $_GET['statut'] ?? null];
        $this->render('Inventaire::maintenances/index', [
            'maintenances' => $this->service->lister($etab, array_filter($filters)),
            'dues'         => $this->service->duesThisMonth($etab),
        ]);
    }

    public function create(): void
    {
        $this->requirePermission('inventaire.maintenance.create');
        $etab    = (int)($this->user['etablissement_id'] ?? 1);
        $filters = ArticleFiltersDTO::fromRequest(['type' => 'equipement']);
        $this->render('Inventaire::maintenances/form', [
            'maintenance' => null,
            'articles'    => $this->articles->lister($filters, $etab)['items'],
        ]);
    }

    public function store(): void
    {
        $this->requirePermission('inventaire.maintenance.create');
        $this->verifyCsrf();
        $etab = (int)($this->user['etablissement_id'] ?? 1);
        try {
            $dto = MaintenanceDTO::fromRequest($_POST);
            $this->service->planifier($dto, (int)$this->user['id'], $etab);
            $this->redirect('/v2/inventaire/maintenances');
        } catch (\RuntimeException $e) {
            $filters = ArticleFiltersDTO::fromRequest(['type' => 'equipement']);
            $this->render('Inventaire::maintenances/form', [
                'error'    => $e->getMessage(),
                'maintenance' => null,
                'articles' => $this->articles->lister($filters, $etab)['items'],
            ]);
        }
    }

    public function demarrer(int $id): void
    {
        $this->requirePermission('inventaire.maintenance.edit');
        $this->verifyCsrf();
        try { $this->service->demarrer($id, (int)$this->user['id']); } catch (\RuntimeException $e) {}
        $this->redirect('/v2/inventaire/maintenances');
    }

    public function terminer(int $id): void
    {
        $this->requirePermission('inventaire.maintenance.edit');
        $this->verifyCsrf();
        $etab = (int)($this->user['etablissement_id'] ?? 1);
        try {
            $this->service->terminer(
                $id,
                (float)($_POST['cout'] ?? 0),
                $_POST['rapport'] ?? '',
                (int)$this->user['id'],
                $etab,
            );
        } catch (\RuntimeException $e) {}
        $this->redirect('/v2/inventaire/maintenances');
    }

    public function annuler(int $id): void
    {
        $this->requirePermission('inventaire.maintenance.edit');
        $this->verifyCsrf();
        try { $this->service->annuler($id, (int)$this->user['id']); } catch (\RuntimeException $e) {}
        $this->redirect('/v2/inventaire/maintenances');
    }
}

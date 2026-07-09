<?php

declare(strict_types=1);

namespace App\Modules\Inventaire\Controllers;

use Core\Controller;
use App\Modules\Inventaire\Services\CategorieService;

class CategorieController extends Controller
{
    private CategorieService $service;

    public function __construct()
    {
        $this->service = new CategorieService();
    }

    public function index(): void
    {
        $this->requirePermission('inventaire.view');
        $etab = (int)($this->user['etablissement_id'] ?? 1);
        $this->render('Inventaire::categories/index', [
            'categories' => $this->service->lister($etab),
        ]);
    }

    public function store(): void
    {
        $this->requirePermission('inventaire.create');
        $this->verifyCsrf();
        $etab = (int)($this->user['etablissement_id'] ?? 1);
        try {
            $this->service->creer($_POST, (int)$this->user['id'], $etab);
        } catch (\RuntimeException $e) { }
        $this->redirect('/v2/inventaire/categories');
    }

    public function update(int $id): void
    {
        $this->requirePermission('inventaire.edit');
        $this->verifyCsrf();
        try {
            $this->service->modifier($id, $_POST, (int)$this->user['id']);
        } catch (\RuntimeException $e) { }
        $this->redirect('/v2/inventaire/categories');
    }

    public function destroy(int $id): void
    {
        $this->requirePermission('inventaire.delete');
        $this->verifyCsrf();
        try {
            $this->service->supprimer($id, (int)$this->user['id']);
        } catch (\RuntimeException $e) { }
        $this->redirect('/v2/inventaire/categories');
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Inventaire\Controllers;

use Core\Controller;
use App\Modules\Inventaire\Services\FournisseurService;
use App\Modules\Inventaire\DTO\FournisseurDTO;

class FournisseurController extends Controller
{
    private FournisseurService $service;

    public function __construct()
    {
        $this->service = new FournisseurService();
    }

    public function index(): void
    {
        $this->requirePermission('inventaire.view');
        $etab = (int)($this->user['etablissement_id'] ?? 1);
        $this->render('Inventaire::fournisseurs/index', [
            'fournisseurs' => $this->service->lister($etab),
        ]);
    }

    public function show(int $id): void
    {
        $this->requirePermission('inventaire.view');
        $f = $this->service->trouver($id);
        if (!$f) { $this->redirect('/v2/inventaire/fournisseurs'); return; }
        $this->render('Inventaire::fournisseurs/show', ['fournisseur' => $f]);
    }

    public function create(): void
    {
        $this->requirePermission('inventaire.create');
        $this->render('Inventaire::fournisseurs/form', ['fournisseur' => null]);
    }

    public function store(): void
    {
        $this->requirePermission('inventaire.create');
        $this->verifyCsrf();
        $etab = (int)($this->user['etablissement_id'] ?? 1);
        try {
            $dto = FournisseurDTO::fromRequest($_POST);
            $id  = $this->service->creer($dto, (int)$this->user['id'], $etab);
            $this->redirect("/v2/inventaire/fournisseurs/{$id}");
        } catch (\RuntimeException $e) {
            $this->render('Inventaire::fournisseurs/form', ['error' => $e->getMessage(), 'fournisseur' => null]);
        }
    }

    public function edit(int $id): void
    {
        $this->requirePermission('inventaire.edit');
        $f = $this->service->trouver($id);
        if (!$f) { $this->redirect('/v2/inventaire/fournisseurs'); return; }
        $this->render('Inventaire::fournisseurs/form', ['fournisseur' => $f]);
    }

    public function update(int $id): void
    {
        $this->requirePermission('inventaire.edit');
        $this->verifyCsrf();
        try {
            $dto = FournisseurDTO::fromRequest($_POST);
            $this->service->modifier($id, $dto, (int)$this->user['id']);
            $this->redirect("/v2/inventaire/fournisseurs/{$id}");
        } catch (\RuntimeException $e) {
            $f = $this->service->trouver($id);
            $this->render('Inventaire::fournisseurs/form', ['error' => $e->getMessage(), 'fournisseur' => $f]);
        }
    }

    public function bloquer(int $id): void
    {
        $this->requirePermission('inventaire.edit');
        $this->verifyCsrf();
        try { $this->service->bloquer($id, (int)$this->user['id']); } catch (\RuntimeException $e) {}
        $this->redirect('/v2/inventaire/fournisseurs');
    }

    public function destroy(int $id): void
    {
        $this->requirePermission('inventaire.delete');
        $this->verifyCsrf();
        try { $this->service->archiver($id, (int)$this->user['id']); } catch (\RuntimeException $e) {}
        $this->redirect('/v2/inventaire/fournisseurs');
    }
}

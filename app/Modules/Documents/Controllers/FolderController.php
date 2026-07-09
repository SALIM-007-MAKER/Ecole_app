<?php

declare(strict_types=1);

namespace App\Modules\Documents\Controllers;

use Core\Controller;
use App\Modules\Documents\DTO\FolderDTO;
use App\Modules\Documents\Policies\FolderPolicy;
use App\Modules\Documents\Services\FolderService;

class FolderController extends Controller
{
    private FolderService $service;
    private FolderPolicy  $policy;

    public function __construct()
    {
        parent::__construct();
        $this->service = new FolderService();
        $this->policy  = new FolderPolicy();
    }

    public function index(): void
    {
        $this->requirePermission('document.view');
        $moduleSource = $_GET['module_source'] ?? null;

        $this->render('Documents::folders/index', [
            'arbre'       => $this->service->arbre($moduleSource),
            'moduleSource'=> $moduleSource,
            'canCreate'   => $this->policy->canCreate($this->user),
            'canDelete'   => $this->policy->canDelete($this->user),
        ]);
    }

    public function store(): void
    {
        $this->requirePermission('folder.create');
        $this->verifyCsrf();

        $dto    = FolderDTO::fromRequest($_POST);
        $errors = $dto->validate();
        if ($errors) {
            $this->json(['success' => false, 'errors' => $errors], 422);
            return;
        }

        try {
            $id = $this->service->creer($dto, (int)$this->user['id']);
            $this->json(['success' => true, 'id' => $id]);
        } catch (\RuntimeException $e) {
            $this->json(['success' => false, 'errors' => ['nom' => $e->getMessage()]], 422);
        }
    }

    public function update(int $id): void
    {
        $this->requirePermission('folder.create');
        $this->verifyCsrf();

        $dto = FolderDTO::fromRequest($_POST);
        $this->service->modifier($id, $dto, (int)$this->user['id']);
        $this->json(['success' => true]);
    }

    public function destroy(int $id): void
    {
        $this->requirePermission('folder.delete');
        $this->verifyCsrf();

        try {
            $this->service->supprimer($id, (int)$this->user['id']);
            $this->json(['success' => true]);
        } catch (\RuntimeException $e) {
            $this->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function breadcrumb(int $id): void
    {
        $this->requirePermission('document.view');
        $this->json(['breadcrumb' => $this->service->breadcrumb($id)]);
    }
}

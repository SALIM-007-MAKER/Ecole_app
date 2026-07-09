<?php

declare(strict_types=1);

namespace App\Modules\Documents\Controllers;

use Core\Controller;
use App\Modules\Documents\Policies\DocumentPolicy;
use App\Modules\Documents\Services\DocumentService;

class TrashController extends Controller
{
    private DocumentService $service;
    private DocumentPolicy  $policy;

    public function __construct()
    {
        parent::__construct();
        $this->service = new DocumentService();
        $this->policy  = new DocumentPolicy();
    }

    public function index(): void
    {
        $this->requirePermission('document.view');

        $this->render('Documents::trash/index', [
            'corbeille' => $this->service->corbeille(),
            'canRestore'=> $this->policy->canRestore($this->user),
            'canPurge'  => $this->policy->canPurge($this->user),
        ]);
    }

    public function trash(int $id): void
    {
        $this->requirePermission('document.delete');
        $this->verifyCsrf();

        if (!$this->policy->canTrash($this->user)) {
            $this->json(['success' => false], 403);
            return;
        }
        $this->service->mettreEnCorbeille($id, (int)$this->user['id']);
        $this->json(['success' => true]);
    }

    public function restore(int $id): void
    {
        $this->requirePermission('document.restore');
        $this->verifyCsrf();

        $this->service->restaurer($id, (int)$this->user['id']);
        $this->json(['success' => true]);
    }

    public function purge(int $id): void
    {
        $this->requirePermission('document.admin');
        $this->verifyCsrf();

        if (!$this->policy->canPurge($this->user)) {
            $this->json(['success' => false], 403);
            return;
        }
        $this->service->purger($id, (int)$this->user['id']);
        $this->json(['success' => true]);
    }

    public function purgeAll(): void
    {
        $this->requirePermission('document.admin');
        $this->verifyCsrf();

        if (!$this->policy->canPurge($this->user)) {
            $this->json(['success' => false], 403);
            return;
        }
        $count = $this->service->viderCorbeille((int)$this->user['id']);
        $this->json(['success' => true, 'count' => $count]);
    }
}

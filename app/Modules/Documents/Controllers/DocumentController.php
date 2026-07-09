<?php

declare(strict_types=1);

namespace App\Modules\Documents\Controllers;

use Core\Controller;
use App\Modules\Documents\DTO\DocumentDTO;
use App\Modules\Documents\DTO\DocumentFiltersDTO;
use App\Modules\Documents\Policies\DocumentPolicy;
use App\Modules\Documents\Services\DocumentService;
use App\Modules\Documents\Services\FolderService;

class DocumentController extends Controller
{
    private DocumentService $service;
    private FolderService   $folders;
    private DocumentPolicy  $policy;

    public function __construct()
    {
        parent::__construct();
        $this->service = new DocumentService();
        $this->folders = new FolderService();
        $this->policy  = new DocumentPolicy();
    }

    public function index(): void
    {
        $this->requirePermission('document.view');

        $filters = DocumentFiltersDTO::fromRequest($_GET);
        $result  = $this->service->paginer($filters);

        $this->render('Documents::documents/index', [
            'documents'  => $result['data'],
            'pagination' => $result,
            'filters'    => $filters,
            'stats'      => $this->service->statistiques(),
            'categories' => $this->service->categories(),
            'canCreate'  => $this->policy->canCreate($this->user),
            'canAdmin'   => $this->policy->canAdmin($this->user),
        ]);
    }

    public function show(int $id): void
    {
        $this->requirePermission('document.view');
        $doc = $this->service->trouver($id);
        if ($doc === null) { $this->redirect('/v2/documents'); return; }

        if (!$this->policy->canView($this->user, $doc)) {
            $this->redirect('/v2/documents');
            return;
        }

        $this->render('Documents::documents/show', [
            'document'   => $doc,
            'versions'   => $this->service->listerVersions($id),
            'historique' => $this->service->historique($id),
            'canUpdate'  => $this->policy->canUpdate($this->user, $doc),
            'canArchive' => $this->policy->canArchive($this->user, $doc),
            'canTrash'   => $this->policy->canTrash($this->user),
            'canShare'   => $this->policy->canShare($this->user, $doc),
            'canSign'    => $this->policy->canSign($this->user),
            'canVersion' => $this->policy->canNewVersion($this->user, $doc),
        ]);
    }

    public function create(): void
    {
        $this->requirePermission('document.create');

        $this->render('Documents::documents/create', [
            'categories'  => $this->service->categories(),
            'folders'     => $this->folders->arbre($_GET['module_source'] ?? null),
            'moduleSource'=> $_GET['module_source'] ?? 'general',
        ]);
    }

    public function store(): void
    {
        $this->requirePermission('document.create');
        $this->verifyCsrf();

        $dto = DocumentDTO::fromRequest($_POST);
        $errors = $dto->validate();

        if ($errors) {
            $this->json(['success' => false, 'errors' => $errors], 422);
            return;
        }

        try {
            $file = $_FILES['fichier'] ?? null;
            if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
                $this->json(['success' => false, 'errors' => ['fichier' => 'Fichier requis.']], 422);
                return;
            }
            $id = $this->service->uploader($dto, $file, (int)$this->user['id']);
            $this->json(['success' => true, 'id' => $id]);
        } catch (\OverflowException $e) {
            $this->json(['success' => false, 'errors' => ['quota' => $e->getMessage()]], 413);
        } catch (\InvalidArgumentException $e) {
            $this->json(['success' => false, 'errors' => ['fichier' => $e->getMessage()]], 422);
        }
    }

    public function edit(int $id): void
    {
        $this->requirePermission('document.update');
        $doc = $this->service->trouver($id);
        if ($doc === null) { $this->redirect('/v2/documents'); return; }

        $this->render('Documents::documents/edit', [
            'document'   => $doc,
            'categories' => $this->service->categories(),
            'folders'    => $this->folders->arbre($doc['module_source']),
        ]);
    }

    public function update(int $id): void
    {
        $this->requirePermission('document.update');
        $this->verifyCsrf();

        $doc = $this->service->trouver($id);
        if ($doc === null) { $this->json(['success' => false], 404); return; }

        $dto = DocumentDTO::fromRequest($_POST);
        $this->service->modifier($id, $dto, (int)$this->user['id']);
        $this->json(['success' => true]);
    }

    public function archive(int $id): void
    {
        $this->requirePermission('document.archive');
        $this->verifyCsrf();

        $doc = $this->service->trouver($id);
        if ($doc === null || !$this->policy->canArchive($this->user, $doc)) {
            $this->json(['success' => false], 403);
            return;
        }

        $this->service->archiver($id, (int)$this->user['id']);
        $this->json(['success' => true]);
    }

    public function restore(int $id): void
    {
        $this->requirePermission('document.restore');
        $this->verifyCsrf();

        $this->service->restaurer($id, (int)$this->user['id']);
        $this->json(['success' => true]);
    }

    public function download(int $id): void
    {
        $this->requirePermission('document.view');
        $doc = $this->service->trouver($id);
        if ($doc === null || !$this->policy->canDownload($this->user, $doc)) {
            http_response_code(403); exit;
        }
        $this->service->telecharger($doc);
    }

    public function version(int $id): void
    {
        $this->requirePermission('document.version');
        $this->verifyCsrf();

        $doc = $this->service->trouver($id);
        if ($doc === null || !$this->policy->canNewVersion($this->user, $doc)) {
            $this->json(['success' => false], 403);
            return;
        }

        $file = $_FILES['fichier'] ?? null;
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            $this->json(['success' => false, 'errors' => ['fichier' => 'Fichier requis.']], 422);
            return;
        }

        $this->service->nouvelleVersion($id, $file, $_POST['notes'] ?? null, (int)$this->user['id']);
        $this->json(['success' => true]);
    }
}

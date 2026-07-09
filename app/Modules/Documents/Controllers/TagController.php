<?php

declare(strict_types=1);

namespace App\Modules\Documents\Controllers;

use Core\Controller;
use App\Modules\Documents\DTO\TagDTO;
use App\Modules\Documents\Services\TagService;

class TagController extends Controller
{
    private TagService $service;

    public function __construct()
    {
        parent::__construct();
        $this->service = new TagService();
    }

    public function index(): void
    {
        $this->requirePermission('document.view');
        $this->json($this->service->tous());
    }

    public function store(): void
    {
        $this->requirePermission('document.create');
        $this->verifyCsrf();

        $dto    = TagDTO::fromRequest($_POST);
        $errors = $dto->validate();
        if ($errors) {
            $this->json(['success' => false, 'errors' => $errors], 422);
            return;
        }

        $id = $this->service->creer($dto, (int)$this->user['id']);
        $this->json(['success' => true, 'id' => $id]);
    }

    public function attach(int $documentId): void
    {
        $this->requirePermission('document.update');
        $this->verifyCsrf();

        $tagIds = array_map('intval', (array)($_POST['tag_ids'] ?? []));
        $this->service->attacher($documentId, $tagIds, (int)$this->user['id']);
        $this->json(['success' => true]);
    }

    public function sync(int $documentId): void
    {
        $this->requirePermission('document.update');
        $this->verifyCsrf();

        $tagIds = array_map('intval', (array)($_POST['tag_ids'] ?? []));
        $this->service->synchroniser($documentId, $tagIds, (int)$this->user['id']);
        $this->json(['success' => true]);
    }
}

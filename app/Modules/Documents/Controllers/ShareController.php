<?php

declare(strict_types=1);

namespace App\Modules\Documents\Controllers;

use Core\Controller;
use App\Modules\Documents\DTO\ShareDTO;
use App\Modules\Documents\Policies\SharePolicy;
use App\Modules\Documents\Services\DocumentService;
use App\Modules\Documents\Services\ShareService;

class ShareController extends Controller
{
    private ShareService    $service;
    private DocumentService $docs;
    private SharePolicy     $policy;

    public function __construct()
    {
        parent::__construct();
        $this->service = new ShareService();
        $this->docs    = new DocumentService();
        $this->policy  = new SharePolicy();
    }

    public function index(int $documentId): void
    {
        $this->requirePermission('document.share');

        $doc = $this->docs->trouver($documentId);
        if ($doc === null) { $this->redirect('/v2/documents'); return; }

        $this->render('Documents::shares/index', [
            'document' => $doc,
            'partages' => $this->service->listerPartages($documentId),
            'canShare' => $this->policy->canShare($this->user),
        ]);
    }

    public function store(int $documentId): void
    {
        $this->requirePermission('document.share');
        $this->verifyCsrf();

        if (!$this->policy->canShare($this->user)) {
            $this->json(['success' => false], 403);
            return;
        }

        $dto    = ShareDTO::fromRequest(array_merge($_POST, ['document_id' => $documentId]));
        $errors = $dto->validate();
        if ($errors) {
            $this->json(['success' => false, 'errors' => $errors], 422);
            return;
        }

        $result = $this->service->partager($dto, (int)$this->user['id']);
        $this->json(['success' => true, 'partage' => $result]);
    }

    public function revoke(int $partageId): void
    {
        $this->requirePermission('document.share');
        $this->verifyCsrf();

        $partage = $this->service->trouverPartage($partageId);
        if ($partage === null) { $this->json(['success' => false], 404); return; }

        if (!$this->policy->canRevoke($this->user, $partage)) {
            $this->json(['success' => false], 403);
            return;
        }

        $this->service->revoquer($partageId, (int)$this->user['id']);
        $this->json(['success' => true]);
    }

    public function accessByToken(string $token): void
    {
        $result = $this->service->accederParToken($token);
        if ($result === null) {
            http_response_code(404);
            echo "Lien invalide ou expiré.";
            return;
        }
        $this->render('Documents::shares/public', ['document' => $result['document'], 'partage' => $result['partage']], 'none');
    }
}

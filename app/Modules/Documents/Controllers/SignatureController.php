<?php

declare(strict_types=1);

namespace App\Modules\Documents\Controllers;

use Core\Controller;
use App\Modules\Documents\Policies\DocumentPolicy;
use App\Modules\Documents\Services\DocumentService;
use App\Modules\Documents\Services\SignatureService;

class SignatureController extends Controller
{
    private SignatureService $service;
    private DocumentService  $docs;
    private DocumentPolicy   $policy;

    public function __construct()
    {
        parent::__construct();
        $this->service = new SignatureService();
        $this->docs    = new DocumentService();
        $this->policy  = new DocumentPolicy();
    }

    public function index(int $documentId): void
    {
        $this->requirePermission('document.view');
        $doc = $this->docs->trouver($documentId);
        if ($doc === null) { $this->redirect('/v2/documents'); return; }

        $this->render('Documents::signatures/index', [
            'document'    => $doc,
            'signataires' => $this->service->signataires($documentId),
            'canRequest'  => $this->policy->canRequestSignature($this->user),
        ]);
    }

    public function request(int $documentId): void
    {
        $this->requirePermission('document.admin');
        $this->verifyCsrf();

        if (!$this->policy->canRequestSignature($this->user)) {
            $this->json(['success' => false], 403);
            return;
        }

        $signataires = $_POST['signataires'] ?? [];
        if (empty($signataires)) {
            $this->json(['success' => false, 'errors' => ['signataires' => 'Au moins un signataire requis.']], 422);
            return;
        }

        $this->service->demanderSignature($documentId, $signataires, (int)$this->user['id']);
        $this->json(['success' => true]);
    }

    public function sign(string $token): void
    {
        try {
            $this->service->signer($token, (int)$this->user['id']);
            $this->json(['success' => true]);
        } catch (\InvalidArgumentException $e) {
            $this->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function reject(string $token): void
    {
        $this->verifyCsrf();
        try {
            $this->service->rejeter($token, $_POST['motif'] ?? null, (int)$this->user['id']);
            $this->json(['success' => true]);
        } catch (\InvalidArgumentException $e) {
            $this->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }
}

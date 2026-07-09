<?php

declare(strict_types=1);

namespace App\Modules\Documents\Controllers;

use Core\Controller;
use App\Modules\Documents\Policies\DocumentPolicy;
use App\Modules\Documents\Services\DocumentService;
use App\Modules\Documents\Services\PreviewService;

class PreviewController extends Controller
{
    private PreviewService  $service;
    private DocumentService $docs;
    private DocumentPolicy  $policy;

    public function __construct()
    {
        parent::__construct();
        $this->service = new PreviewService();
        $this->docs    = new DocumentService();
        $this->policy  = new DocumentPolicy();
    }

    public function show(int $id): void
    {
        $this->requirePermission('document.view');
        $doc = $this->docs->trouver($id);
        if ($doc === null || !$this->policy->canView($this->user, $doc)) {
            http_response_code(403); exit;
        }

        $preview = $this->service->generer($doc);
        $this->render('Documents::preview/show', [
            'document' => $doc,
            'preview'  => $preview,
        ]);
    }

    public function serveFile(string $filename): void
    {
        $this->requirePermission('document.view');

        $chemin = $this->service->cheminSurveilleAcces($filename, $this->user);
        if ($chemin === null) { http_response_code(403); exit; }

        $mime = mime_content_type($chemin) ?: 'application/octet-stream';
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($chemin));
        header('Content-Disposition: inline; filename="' . basename($chemin) . '"');
        header('X-Content-Type-Options: nosniff');
        readfile($chemin);
        exit;
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Communication\Controllers;

use App\Modules\Communication\DTO\TemplateDTO;
use App\Modules\Communication\Services\TemplateService;
use Core\Controller;

class TemplateController extends Controller
{
    private TemplateService $service;

    public function __construct()
    {
        parent::__construct();
        $this->service = new TemplateService();
    }

    public function index(): void
    {
        $this->requirePermission('communication.manage_templates');
        $canal  = $_GET['canal']  ?? null;
        $module = $_GET['module'] ?? null;

        $templates = $this->service->lister($canal, $module);
        $this->render('Communication::templates/index', [
            'templates'    => $templates,
            'filtre_canal' => $canal,
            'titre'        => 'Templates de communication',
        ]);
    }

    public function store(): void
    {
        $this->requirePermission('communication.manage_templates');
        $this->verifyCsrf();

        $dto    = TemplateDTO::fromRequest($_POST);
        $errors = $dto->validate();
        if (!empty($errors)) {
            $this->json(['success' => false, 'errors' => $errors], 422);
            return;
        }

        $id = $this->service->creer($dto, (int) $this->user['id']);
        $this->json(['success' => true, 'id' => $id]);
    }

    public function show(int $id): void
    {
        $this->requirePermission('communication.manage_templates');
        $template = $this->service->trouver($id);
        if ($template === null) {
            $this->redirect('/v2/communication/templates');
            return;
        }
        $this->render('Communication::templates/form', [
            'template' => $template,
            'titre'    => 'Modifier template',
        ]);
    }

    public function update(int $id): void
    {
        $this->requirePermission('communication.manage_templates');
        $this->verifyCsrf();

        $dto    = TemplateDTO::fromRequest($_POST);
        $errors = $dto->validate();
        if (!empty($errors)) {
            $this->json(['success' => false, 'errors' => $errors], 422);
            return;
        }

        $this->service->modifier($id, $dto, (int) $this->user['id']);
        $this->json(['success' => true]);
    }

    public function preview(int $id): void
    {
        $this->requirePermission('communication.manage_templates');
        $template = $this->service->trouver($id);
        if ($template === null) {
            $this->json(['error' => 'Template introuvable'], 404);
            return;
        }
        $variables = $_POST['variables'] ?? [];
        $rendered  = $this->service->render($template, $variables);

        $this->render('Communication::templates/preview', [
            'template' => $template,
            'rendered' => $rendered,
            'titre'    => 'Aperçu template',
        ]);
    }

    public function destroy(int $id): void
    {
        $this->requirePermission('communication.manage_templates');
        $this->verifyCsrf();
        $this->service->archiver($id, (int) $this->user['id']);
        $this->json(['success' => true]);
    }

    public function renderPreview(string $code): void
    {
        $this->requirePermission('communication.manage_templates');
        $canal   = $_GET['canal']  ?? 'internal';
        $tpl     = $this->service->trouverParCode($code, $canal);
        if ($tpl === null) {
            $this->json(['error' => 'Template introuvable'], 404);
            return;
        }
        $vars     = $_GET['vars'] ?? [];
        $rendered = $this->service->render($tpl, is_array($vars) ? $vars : []);
        $this->json(['rendered' => $rendered]);
    }
}

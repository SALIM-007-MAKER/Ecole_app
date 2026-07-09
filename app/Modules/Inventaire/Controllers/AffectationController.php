<?php

declare(strict_types=1);

namespace App\Modules\Inventaire\Controllers;

use Core\Controller;
use App\Modules\Inventaire\Services\AffectationService;
use App\Modules\Inventaire\Services\ArticleService;
use App\Modules\Inventaire\Services\EmplacementService;
use App\Modules\Inventaire\DTO\AffectationDTO;
use App\Modules\Inventaire\DTO\ArticleFiltersDTO;

class AffectationController extends Controller
{
    private AffectationService $service;
    private ArticleService     $articles;
    private EmplacementService $emplacements;

    public function __construct()
    {
        $this->service      = new AffectationService();
        $this->articles     = new ArticleService();
        $this->emplacements = new EmplacementService();
    }

    public function index(): void
    {
        $this->requirePermission('inventaire.affectation.view');
        $etab   = (int)($this->user['etablissement_id'] ?? 1);
        $statut = $_GET['statut'] ?? null;
        $this->render('Inventaire::affectations/index', [
            'affectations' => $this->service->lister($etab, $statut),
            'statut'       => $statut,
        ]);
    }

    public function create(): void
    {
        $this->requirePermission('inventaire.affectation.create');
        $etab    = (int)($this->user['etablissement_id'] ?? 1);
        $filters = ArticleFiltersDTO::fromRequest(['type' => 'durable']);
        $this->render('Inventaire::affectations/form', [
            'articles'     => $this->articles->lister($filters, $etab)['items'],
            'emplacements' => $this->emplacements->lister($etab),
        ]);
    }

    public function store(): void
    {
        $this->requirePermission('inventaire.affectation.create');
        $this->verifyCsrf();
        $etab = (int)($this->user['etablissement_id'] ?? 1);
        try {
            $dto = AffectationDTO::fromRequest($_POST);
            $id  = $this->service->affecter($dto, (int)$this->user['id'], $etab);
            $this->redirect('/v2/inventaire/affectations');
        } catch (\RuntimeException $e) {
            $filters = ArticleFiltersDTO::fromRequest(['type' => 'durable']);
            $this->render('Inventaire::affectations/form', [
                'error'        => $e->getMessage(),
                'articles'     => $this->articles->lister($filters, $etab)['items'],
                'emplacements' => $this->emplacements->lister($etab),
            ]);
        }
    }

    public function retourner(int $id): void
    {
        $this->requirePermission('inventaire.affectation.return');
        $this->verifyCsrf();
        $etab = (int)($this->user['etablissement_id'] ?? 1);
        try {
            $this->service->retourner($id, $_POST['etat'] ?? 'bon', (int)$this->user['id'], $etab);
        } catch (\RuntimeException $e) {}
        $this->redirect('/v2/inventaire/affectations');
    }

    public function declararerPerdue(int $id): void
    {
        $this->requirePermission('inventaire.affectation.lost');
        $this->verifyCsrf();
        $etab = (int)($this->user['etablissement_id'] ?? 1);
        try {
            $this->service->declararerPerdue($id, (int)$this->user['id'], $etab);
        } catch (\RuntimeException $e) {}
        $this->redirect('/v2/inventaire/affectations');
    }
}

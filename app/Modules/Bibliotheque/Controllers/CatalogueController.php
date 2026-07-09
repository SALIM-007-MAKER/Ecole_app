<?php

declare(strict_types=1);

namespace App\Modules\Bibliotheque\Controllers;

use App\Modules\Bibliotheque\DTO\OuvrageDTO;
use App\Modules\Bibliotheque\DTO\OuvrageFiltersDTO;
use App\Modules\Bibliotheque\Policies\BiblioPolicy;
use App\Modules\Bibliotheque\Services\CatalogueService;
use Core\Controller;

class CatalogueController extends Controller
{
    private CatalogueService $service;
    private BiblioPolicy     $policy;

    public function __construct()
    {
        parent::__construct();
        $this->service = new CatalogueService();
        $this->policy  = new BiblioPolicy();
    }

    public function index(): void
    {
        $this->requirePermission('biblio.view');
        $filters = OuvrageFiltersDTO::fromRequest($_GET);
        $etablissementId = (int)($this->user['etablissement_id'] ?? 1);
        $ouvrages = $this->service->lister($filters, $etablissementId);

        $this->render('Bibliotheque::catalogue/index', [
            'ouvrages'   => $ouvrages,
            'filters'    => $filters,
            'categories' => $this->service->listerCategories($etablissementId),
            'auteurs'    => $this->service->listerAuteurs($etablissementId),
            'titre'      => 'Catalogue',
        ]);
    }

    public function show(int $id): void
    {
        $this->requirePermission('biblio.view');
        $etablissementId = (int)($this->user['etablissement_id'] ?? 1);
        $ouvrage = $this->service->trouver($id);
        if ($ouvrage === null) {
            $this->redirect('/v2/bibliotheque/catalogue');
            return;
        }
        $this->render('Bibliotheque::catalogue/show', [
            'ouvrage'    => $ouvrage,
            'similaires' => $this->service->suggestions($ouvrage['titre'] ?? '', $etablissementId),
            'titre'      => $ouvrage['titre'],
        ]);
    }

    public function create(): void
    {
        $this->requirePermission('biblio.manage_catalogue');
        $etablissementId = (int)($this->user['etablissement_id'] ?? 1);
        $this->render('Bibliotheque::catalogue/form', [
            'ouvrage'    => null,
            'categories' => $this->service->listerCategories($etablissementId),
            'auteurs'    => $this->service->listerAuteurs($etablissementId),
            'editeurs'   => $this->service->listerEditeurs($etablissementId),
            'titre'      => 'Ajouter un ouvrage',
        ]);
    }

    public function store(): void
    {
        $this->requirePermission('biblio.manage_catalogue');
        $this->verifyCsrf();

        $etablissementId = (int)($this->user['etablissement_id'] ?? 1);
        $dto = OuvrageDTO::fromRequest($_POST);
        $id  = $this->service->ajouterOuvrage($dto, (int)$this->user['id'], $etablissementId);

        $this->redirect('/v2/bibliotheque/catalogue/' . $id);
    }

    public function edit(int $id): void
    {
        $this->requirePermission('biblio.manage_catalogue');
        $etablissementId = (int)($this->user['etablissement_id'] ?? 1);
        $ouvrage = $this->service->trouver($id);
        if ($ouvrage === null) {
            $this->redirect('/v2/bibliotheque/catalogue');
            return;
        }
        $this->render('Bibliotheque::catalogue/form', [
            'ouvrage'    => $ouvrage,
            'categories' => $this->service->listerCategories($etablissementId),
            'auteurs'    => $this->service->listerAuteurs($etablissementId),
            'editeurs'   => $this->service->listerEditeurs($etablissementId),
            'titre'      => 'Modifier : ' . $ouvrage['titre'],
        ]);
    }

    public function update(int $id): void
    {
        $this->requirePermission('biblio.manage_catalogue');
        $this->verifyCsrf();

        $dto = OuvrageDTO::fromRequest($_POST);
        $this->service->modifierOuvrage($id, $dto, (int)$this->user['id']);

        $this->redirect('/v2/bibliotheque/catalogue/' . $id);
    }

    public function archive(int $id): void
    {
        $this->requirePermission('biblio.manage_catalogue');
        $this->verifyCsrf();

        $this->service->archiverOuvrage($id, (int)$this->user['id']);
        $this->json(['success' => true]);
    }

    public function search(): void
    {
        $this->requirePermission('biblio.search');
        $etablissementId = (int)($this->user['etablissement_id'] ?? 1);
        $terme = trim($_GET['q'] ?? '');
        $suggestions = $this->service->suggestions($terme, $etablissementId);
        $this->json(['suggestions' => $suggestions]);
    }
}

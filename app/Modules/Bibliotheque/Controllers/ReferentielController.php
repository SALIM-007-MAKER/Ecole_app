<?php

declare(strict_types=1);

namespace App\Modules\Bibliotheque\Controllers;

use App\Modules\Bibliotheque\Policies\BiblioPolicy;
use App\Modules\Bibliotheque\Services\CatalogueService;
use Core\Controller;

class ReferentielController extends Controller
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
        $this->requirePermission('biblio.manage_catalogue');
        $etab = (int)($this->user['etablissement_id'] ?? 1);
        $this->render('Bibliotheque::referentiels/index', [
            'auteurs'    => $this->service->listerAuteurs($etab),
            'editeurs'   => $this->service->listerEditeurs($etab),
            'categories' => $this->service->listerCategories($etab),
            'tags'       => $this->service->listerTags($etab),
            'titre'      => 'Référentiels',
        ]);
    }

    /* Auteurs */
    public function storeAuteur(): void
    {
        $this->requirePermission('biblio.manage_catalogue');
        $this->verifyCsrf();
        $etab = (int)($this->user['etablissement_id'] ?? 1);
        $id = $this->service->ajouterAuteur([
            'nom'        => $_POST['nom'] ?? '',
            'prenom'     => $_POST['prenom'] ?? null,
            'biographie' => $_POST['biographie'] ?? null,
        ], $etab);
        $this->json(['success' => true, 'id' => $id]);
    }

    public function updateAuteur(int $id): void
    {
        $this->requirePermission('biblio.manage_catalogue');
        $this->verifyCsrf();
        $this->service->modifierAuteur($id, [
            'nom'        => $_POST['nom'] ?? '',
            'prenom'     => $_POST['prenom'] ?? null,
            'biographie' => $_POST['biographie'] ?? null,
        ]);
        $this->json(['success' => true]);
    }

    public function archiveAuteur(int $id): void
    {
        $this->requirePermission('biblio.manage_catalogue');
        $this->verifyCsrf();
        $this->service->archiverAuteur($id);
        $this->json(['success' => true]);
    }

    /* Éditeurs */
    public function storeEditeur(): void
    {
        $this->requirePermission('biblio.manage_catalogue');
        $this->verifyCsrf();
        $etab = (int)($this->user['etablissement_id'] ?? 1);
        $id = $this->service->ajouterEditeur([
            'nom'     => $_POST['nom'] ?? '',
            'adresse' => $_POST['adresse'] ?? null,
            'email'   => $_POST['email'] ?? null,
        ], $etab);
        $this->json(['success' => true, 'id' => $id]);
    }

    public function updateEditeur(int $id): void
    {
        $this->requirePermission('biblio.manage_catalogue');
        $this->verifyCsrf();
        $this->service->modifierEditeur($id, [
            'nom'     => $_POST['nom'] ?? '',
            'adresse' => $_POST['adresse'] ?? null,
            'email'   => $_POST['email'] ?? null,
        ]);
        $this->json(['success' => true]);
    }

    public function archiveEditeur(int $id): void
    {
        $this->requirePermission('biblio.manage_catalogue');
        $this->verifyCsrf();
        $this->service->archiverEditeur($id);
        $this->json(['success' => true]);
    }

    /* Catégories */
    public function storeCategorie(): void
    {
        $this->requirePermission('biblio.manage_catalogue');
        $this->verifyCsrf();
        $etab = (int)($this->user['etablissement_id'] ?? 1);
        $id = $this->service->ajouterCategorie([
            'nom'         => $_POST['nom'] ?? '',
            'description' => $_POST['description'] ?? null,
            'parent_id'   => isset($_POST['parent_id']) ? (int)$_POST['parent_id'] : null,
        ], $etab);
        $this->json(['success' => true, 'id' => $id]);
    }

    public function updateCategorie(int $id): void
    {
        $this->requirePermission('biblio.manage_catalogue');
        $this->verifyCsrf();
        $this->service->modifierCategorie($id, [
            'nom'         => $_POST['nom'] ?? '',
            'description' => $_POST['description'] ?? null,
        ]);
        $this->json(['success' => true]);
    }

    public function archiveCategorie(int $id): void
    {
        $this->requirePermission('biblio.manage_catalogue');
        $this->verifyCsrf();
        $this->service->archiverCategorie($id);
        $this->json(['success' => true]);
    }

    /* Tags */
    public function storeTag(): void
    {
        $this->requirePermission('biblio.manage_catalogue');
        $this->verifyCsrf();
        $etab = (int)($this->user['etablissement_id'] ?? 1);
        $id = $this->service->ajouterTag(['nom' => $_POST['nom'] ?? ''], $etab);
        $this->json(['success' => true, 'id' => $id]);
    }
}

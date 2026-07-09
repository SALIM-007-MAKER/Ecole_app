<?php

declare(strict_types=1);

namespace App\Modules\Inventaire\Controllers;

use Core\Controller;
use App\Modules\Inventaire\Services\CommandeService;
use App\Modules\Inventaire\Services\FournisseurService;
use App\Modules\Inventaire\Services\ArticleService;
use App\Modules\Inventaire\DTO\CommandeDTO;
use App\Modules\Inventaire\DTO\ArticleFiltersDTO;

class CommandeController extends Controller
{
    private CommandeService   $service;
    private FournisseurService $fournisseurs;
    private ArticleService    $articles;

    public function __construct()
    {
        $this->service      = new CommandeService();
        $this->fournisseurs = new FournisseurService();
        $this->articles     = new ArticleService();
    }

    public function index(): void
    {
        $this->requirePermission('inventaire.commande.view');
        $etab   = (int)($this->user['etablissement_id'] ?? 1);
        $statut = $_GET['statut'] ?? null;
        $this->render('Inventaire::commandes/index', [
            'commandes' => $this->service->lister($etab, $statut),
            'statut'    => $statut,
        ]);
    }

    public function show(int $id): void
    {
        $this->requirePermission('inventaire.commande.view');
        $c = $this->service->trouver($id);
        if (!$c) { $this->redirect('/v2/inventaire/commandes'); return; }
        $c['lignes'] = $this->service->lignes($id);
        $this->render('Inventaire::commandes/show', ['commande' => $c]);
    }

    public function create(): void
    {
        $this->requirePermission('inventaire.commande.create');
        $etab = (int)($this->user['etablissement_id'] ?? 1);
        $this->render('Inventaire::commandes/form', [
            'commande'     => null,
            'fournisseurs' => $this->fournisseurs->lister($etab, 'actif'),
            'articles'     => $this->articles->lister(ArticleFiltersDTO::fromRequest([]), $etab)['items'],
        ]);
    }

    public function store(): void
    {
        $this->requirePermission('inventaire.commande.create');
        $this->verifyCsrf();
        $etab = (int)($this->user['etablissement_id'] ?? 1);
        try {
            $dto   = CommandeDTO::fromRequest($_POST);
            $lignes= $_POST['lignes'] ?? [];
            $id    = $this->service->creer($dto, $lignes, (int)$this->user['id'], $etab);
            $this->redirect("/v2/inventaire/commandes/{$id}");
        } catch (\RuntimeException $e) {
            $this->render('Inventaire::commandes/form', [
                'error'        => $e->getMessage(),
                'commande'     => null,
                'fournisseurs' => $this->fournisseurs->lister($etab, 'actif'),
                'articles'     => $this->articles->lister(ArticleFiltersDTO::fromRequest([]), $etab)['items'],
            ]);
        }
    }

    public function valider(int $id): void
    {
        $this->requirePermission('inventaire.commande.validate');
        $this->verifyCsrf();
        $etab = (int)($this->user['etablissement_id'] ?? 1);
        try { $this->service->valider($id, (int)$this->user['id'], $etab); } catch (\RuntimeException $e) {}
        $this->redirect("/v2/inventaire/commandes/{$id}");
    }

    public function annuler(int $id): void
    {
        $this->requirePermission('inventaire.commande.validate');
        $this->verifyCsrf();
        try { $this->service->annuler($id, (int)$this->user['id']); } catch (\RuntimeException $e) {}
        $this->redirect('/v2/inventaire/commandes');
    }

    public function destroy(int $id): void
    {
        $this->requirePermission('inventaire.delete');
        $this->verifyCsrf();
        try { $this->service->supprimer($id, (int)$this->user['id']); } catch (\RuntimeException $e) {}
        $this->redirect('/v2/inventaire/commandes');
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Inventaire\Controllers;

use Core\Controller;
use App\Modules\Inventaire\Services\InventairePhysiqueService;
use App\Modules\Inventaire\DTO\InventaireDTO;

class InvPhysiqueController extends Controller
{
    private InventairePhysiqueService $service;

    public function __construct()
    {
        $this->service = new InventairePhysiqueService();
    }

    public function index(): void
    {
        $this->requirePermission('inventaire.physique.view');
        $etab = (int)($this->user['etablissement_id'] ?? 1);
        $this->render('Inventaire::inventaires-physiques/index', [
            'inventaires' => $this->service->lister($etab),
        ]);
    }

    public function create(): void
    {
        $this->requirePermission('inventaire.physique.create');
        $this->render('Inventaire::inventaires-physiques/form', []);
    }

    public function store(): void
    {
        $this->requirePermission('inventaire.physique.create');
        $this->verifyCsrf();
        $etab = (int)($this->user['etablissement_id'] ?? 1);
        try {
            $dto = InventaireDTO::fromRequest($_POST);
            $id  = $this->service->ouvrir($dto, (int)$this->user['id'], $etab);
            $this->redirect("/v2/inventaire/inventaires-physiques/{$id}/session");
        } catch (\RuntimeException $e) {
            $this->render('Inventaire::inventaires-physiques/form', ['error' => $e->getMessage()]);
        }
    }

    public function session(int $id): void
    {
        $this->requirePermission('inventaire.physique.view');
        $inv = $this->service->trouver($id);
        if (!$inv) { $this->redirect('/v2/inventaire/inventaires-physiques'); return; }
        $this->render('Inventaire::inventaires-physiques/session', [
            'inventaire' => $inv,
            'lignes'     => $this->service->lignes($id),
        ]);
    }

    public function saisir(int $id): void
    {
        $this->requirePermission('inventaire.physique.count');
        $this->verifyCsrf();
        try {
            $this->service->saisirComptage(
                $id,
                (int)($_POST['ligne_id'] ?? 0),
                (float)($_POST['quantite_comptee'] ?? 0),
                (int)$this->user['id'],
            );
        } catch (\RuntimeException $e) {}
        $this->json(['ok' => true]);
    }

    public function cloture(int $id): void
    {
        $this->requirePermission('inventaire.physique.close');
        $this->verifyCsrf();
        $etab = (int)($this->user['etablissement_id'] ?? 1);
        try {
            $this->service->cloture($id, (int)$this->user['id'], $etab);
        } catch (\RuntimeException $e) {}
        $this->redirect('/v2/inventaire/inventaires-physiques');
    }
}

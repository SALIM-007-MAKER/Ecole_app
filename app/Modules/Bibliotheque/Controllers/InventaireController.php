<?php

declare(strict_types=1);

namespace App\Modules\Bibliotheque\Controllers;

use App\Modules\Bibliotheque\DTO\InventaireDTO;
use App\Modules\Bibliotheque\Policies\BiblioPolicy;
use App\Modules\Bibliotheque\Services\InventaireService;
use Core\Controller;

class InventaireController extends Controller
{
    private InventaireService $service;
    private BiblioPolicy      $policy;

    public function __construct()
    {
        parent::__construct();
        $this->service = new InventaireService();
        $this->policy  = new BiblioPolicy();
    }

    public function index(): void
    {
        $this->requirePermission('biblio.inventory');
        $etablissementId = (int)($this->user['etablissement_id'] ?? 1);
        $sessions = $this->service->lister($etablissementId);

        $this->render('Bibliotheque::inventaire/index', [
            'sessions' => $sessions,
            'titre'    => 'Inventaire',
        ]);
    }

    public function store(): void
    {
        $this->requirePermission('biblio.inventory');
        $this->verifyCsrf();

        $etablissementId = (int)($this->user['etablissement_id'] ?? 1);
        $dto = InventaireDTO::fromRequest($_POST);

        try {
            $id = $this->service->lancerSession($dto, (int)$this->user['id'], $etablissementId);
            $this->json(['success' => true, 'id' => $id]);
        } catch (\RuntimeException $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public function show(int $id): void
    {
        $this->requirePermission('biblio.inventory');
        $session = $this->service->trouver($id);
        if ($session === null) {
            $this->redirect('/v2/bibliotheque/inventaire');
            return;
        }
        $lignes  = $this->service->lignes($id);
        $rapport = $this->service->rapport($id);

        $this->render('Bibliotheque::inventaire/show', [
            'session' => $session,
            'lignes'  => $lignes,
            'rapport' => $rapport,
            'titre'   => 'Session inventaire : ' . $session['nom'],
        ]);
    }

    public function scan(int $id): void
    {
        $this->requirePermission('biblio.inventory');
        $this->verifyCsrf();

        $code    = trim($_POST['code'] ?? '');
        $statut  = $_POST['statut'] ?? 'present';
        $notes   = $_POST['notes'] ?? null;

        try {
            $this->service->scannerExemplaire($id, $code, $statut, (int)$this->user['id'], $notes);
            $this->json(['success' => true]);
        } catch (\RuntimeException $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 404);
        }
    }

    public function terminer(int $id): void
    {
        $this->requirePermission('biblio.inventory');
        $this->verifyCsrf();

        try {
            $rapport = $this->service->terminerSession($id, (int)$this->user['id']);
            $this->json(['success' => true, 'rapport' => $rapport]);
        } catch (\RuntimeException $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 422);
        }
    }
}

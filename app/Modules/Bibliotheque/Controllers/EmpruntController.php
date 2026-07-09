<?php

declare(strict_types=1);

namespace App\Modules\Bibliotheque\Controllers;

use App\Modules\Bibliotheque\DTO\EmpruntDTO;
use App\Modules\Bibliotheque\Policies\BiblioPolicy;
use App\Modules\Bibliotheque\Services\EmpruntService;
use Core\Controller;

class EmpruntController extends Controller
{
    private EmpruntService $service;
    private BiblioPolicy   $policy;

    public function __construct()
    {
        parent::__construct();
        $this->service = new EmpruntService();
        $this->policy  = new BiblioPolicy();
    }

    public function index(): void
    {
        $this->requirePermission('biblio.manage_loans');
        $etablissementId = (int)($this->user['etablissement_id'] ?? 1);
        $page    = (int)($_GET['page'] ?? 1);
        $emprunts = $this->service->listerEnCours($etablissementId, $page);

        $this->render('Bibliotheque::emprunts/index', [
            'emprunts' => $emprunts,
            'page'     => $page,
            'titre'    => 'Emprunts en cours',
        ]);
    }

    public function enRetard(): void
    {
        $this->requirePermission('biblio.manage_loans');
        $etablissementId = (int)($this->user['etablissement_id'] ?? 1);
        $emprunts = $this->service->listerEnRetard($etablissementId);

        $this->render('Bibliotheque::emprunts/index', [
            'emprunts' => $emprunts,
            'filtre'   => 'retard',
            'titre'    => 'Emprunts en retard',
        ]);
    }

    public function create(): void
    {
        $this->requirePermission('biblio.manage_loans');
        $this->render('Bibliotheque::emprunts/create', [
            'titre' => 'Nouvel emprunt',
        ]);
    }

    public function store(): void
    {
        $this->requirePermission('biblio.manage_loans');
        $this->verifyCsrf();

        $etablissementId = (int)($this->user['etablissement_id'] ?? 1);
        $dto = EmpruntDTO::fromRequest($_POST, $etablissementId);

        try {
            $id = $this->service->creerEmprunt($dto, (int)$this->user['id']);
            $this->json(['success' => true, 'id' => $id]);
        } catch (\RuntimeException $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public function show(int $id): void
    {
        $this->requirePermission('biblio.manage_loans');
        $emprunt = $this->service->trouver($id);
        if ($emprunt === null) {
            $this->redirect('/v2/bibliotheque/emprunts');
            return;
        }
        $this->render('Bibliotheque::emprunts/show', [
            'emprunt' => $emprunt,
            'titre'   => 'Emprunt #' . $id,
        ]);
    }

    public function retour(int $id): void
    {
        $this->requirePermission('biblio.manage_loans');
        $this->verifyCsrf();

        $this->service->retournerEmprunt($id, (int)$this->user['id']);
        $this->json(['success' => true]);
    }

    public function prolonger(int $id): void
    {
        $this->requirePermission('biblio.borrow');
        $this->verifyCsrf();

        try {
            $this->service->prolongerEmprunt($id, (int)$this->user['id']);
            $this->json(['success' => true]);
        } catch (\RuntimeException $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public function declarerPerdu(int $id): void
    {
        $this->requirePermission('biblio.manage_loans');
        $this->verifyCsrf();

        $notes = $_POST['notes'] ?? null;
        $this->service->declarerPerdu($id, (int)$this->user['id'], $notes);
        $this->json(['success' => true]);
    }

    public function cronRetards(): void
    {
        $etablissementId = (int)($_GET['etablissement_id'] ?? 1);
        $nb = $this->service->detectionRetards($etablissementId);
        $this->json(['success' => true, 'processed' => $nb]);
    }

    public function cronRappels(): void
    {
        $etablissementId = (int)($_GET['etablissement_id'] ?? 1);
        $rappels = $this->service->rappels($etablissementId);
        $this->json(['success' => true, 'count' => count($rappels)]);
    }
}

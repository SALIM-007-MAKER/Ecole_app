<?php

declare(strict_types=1);

namespace App\Modules\Bibliotheque\Controllers;

use App\Modules\Bibliotheque\Policies\BiblioPolicy;
use App\Modules\Bibliotheque\Services\PenaliteService;
use Core\Controller;

class PenaliteController extends Controller
{
    private PenaliteService $service;
    private BiblioPolicy    $policy;

    public function __construct()
    {
        parent::__construct();
        $this->service = new PenaliteService();
        $this->policy  = new BiblioPolicy();
    }

    public function index(): void
    {
        $this->requirePermission('biblio.manage_penalties');
        $etablissementId = (int)($this->user['etablissement_id'] ?? 1);
        $penalites = $this->service->listerImpayees($etablissementId);
        $total     = $this->service->totalImpayees($etablissementId);

        $this->render('Bibliotheque::penalites/index', [
            'penalites' => $penalites,
            'total'     => $total,
            'mode'      => 'gestion',
            'titre'     => 'Pénalités impayées',
        ]);
    }

    public function mes(): void
    {
        $this->requirePermission('biblio.borrow');
        $penalites = $this->service->listerParUser((int)$this->user['id']);

        $this->render('Bibliotheque::penalites/index', [
            'penalites' => $penalites,
            'mode'      => 'mes',
            'titre'     => 'Mes pénalités',
        ]);
    }

    public function show(int $id): void
    {
        $this->requirePermission('biblio.manage_penalties');
        $penalite = $this->service->trouver($id);
        if ($penalite === null) { $this->json([], 404); return; }
        $this->json($penalite);
    }

    public function payer(int $id): void
    {
        $this->requirePermission('biblio.manage_penalties');
        $this->verifyCsrf();

        $this->service->payerManuellement($id, (int)$this->user['id']);
        $this->json(['success' => true]);
    }

    public function annuler(int $id): void
    {
        $this->requirePermission('biblio.manage_penalties');
        $this->verifyCsrf();

        $this->service->annuler($id, (int)$this->user['id']);
        $this->json(['success' => true]);
    }
}

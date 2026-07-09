<?php
declare(strict_types=1);

namespace App\Modules\Rapports\Controllers;

use Core\Controller;
use App\Modules\Rapports\DTO\PlanificationDTO;
use App\Modules\Rapports\Services\PlanificationService;

class PlanificationController extends Controller
{
    private PlanificationService $service;

    public function __construct()
    {
        $this->service = new PlanificationService();
    }

    public function index(): void
    {
        $this->requirePermission('rapports.planifier');
        $etab  = (int)($this->user['etablissement_id'] ?? 1);
        $page  = max(1, (int)($_GET['page'] ?? 1));
        $liste = $this->service->lister($etab, $page);
        $this->render('Rapports::planifications/index', ['liste' => $liste]);
    }

    public function create(): void
    {
        $this->requirePermission('rapports.planifier');
        $this->render('Rapports::planifications/form', ['planif' => null]);
    }

    public function store(): void
    {
        $this->requirePermission('rapports.planifier');
        $this->verifyCsrf();
        $etab   = (int)($this->user['etablissement_id'] ?? 1);
        $userId = (int)($this->user['id']               ?? 0);
        $dto    = PlanificationDTO::fromRequest($_POST);
        $id     = $this->service->creer($dto, $userId, $etab);
        $this->redirect('/v2/rapports/planifications/' . $id . '?success=1');
    }

    public function show(int $id): void
    {
        $this->requirePermission('rapports.planifier');
        $planif = $this->service->find($id);
        if (!$planif) { $this->redirect('/v2/rapports/planifications?error=not_found'); return; }
        $this->render('Rapports::planifications/show', ['planif' => $planif]);
    }

    public function edit(int $id): void
    {
        $this->requirePermission('rapports.planifier');
        $planif = $this->service->find($id);
        if (!$planif) { $this->redirect('/v2/rapports/planifications'); return; }
        $this->render('Rapports::planifications/form', ['planif' => $planif]);
    }

    public function update(int $id): void
    {
        $this->requirePermission('rapports.planifier');
        $this->verifyCsrf();
        $dto = PlanificationDTO::fromRequest($_POST);
        $this->service->modifier($id, $dto);
        $this->redirect('/v2/rapports/planifications/' . $id . '?success=1');
    }

    public function destroy(int $id): void
    {
        $this->requirePermission('rapports.planifier');
        $this->verifyCsrf();
        $this->service->supprimer($id);
        $this->redirect('/v2/rapports/planifications?success=deleted');
    }

    public function executer(int $id): void
    {
        $this->requirePermission('rapports.planifier');
        $this->verifyCsrf();
        $etab = (int)($this->user['etablissement_id'] ?? 1);
        $this->service->executerPlanifie($id, $etab);
        $this->redirect('/v2/rapports/planifications/' . $id . '?success=executed');
    }
}

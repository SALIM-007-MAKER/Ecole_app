<?php

declare(strict_types=1);

namespace App\Modules\RH\Organisation\Controllers;

use App\Modules\RH\Organisation\DTO\PositionDTO;
use App\Modules\RH\Organisation\DTO\OrganizationFiltersDTO;
use App\Modules\RH\Organisation\Models\OrganizationModel;
use App\Modules\RH\Organisation\Policies\OrganizationPolicy;
use App\Modules\RH\Organisation\Services\PositionService;
use App\Modules\RH\Organisation\Services\DepartmentService;
use Core\Controller;
use Core\Session;

class PositionController extends Controller
{
    private PositionService    $service;
    private DepartmentService  $deptService;
    private OrganizationPolicy $policy;

    public function __construct()
    {
        parent::__construct();
        $this->service     = new PositionService();
        $this->deptService = new DepartmentService();
        $this->policy      = new OrganizationPolicy();
    }

    // ── Liste postes ──────────────────────────────────────────────────────────

    public function index(): void
    {
        $this->requirePermission('organization.view');

        $filters     = OrganizationFiltersDTO::fromRequest($_GET);
        $result      = $this->service->paginate($filters);
        $departements = $this->deptService->findDepartementsActifs();

        $this->render('RH::organisation/postes/index', [
            'postes'       => $result['items'],
            'pagination'   => $result,
            'filters'      => $filters,
            'departements' => $departements,
            'categories'   => PositionDTO::CATEGORIES,
            'niveaux'      => OrganizationModel::NIVEAUX,
            'canCreate'    => $this->policy->canCreate($this->currentUser()),
            'canUpdate'    => $this->policy->canUpdate($this->currentUser()),
            'canArchive'   => $this->policy->canArchive($this->currentUser()),
            'canExport'    => $this->policy->canExport($this->currentUser()),
        ]);
    }

    // ── Création poste ────────────────────────────────────────────────────────

    public function create(): void
    {
        $this->requirePermission('organization.create');

        $departements = $this->deptService->findDepartementsActifs();
        $services     = $this->deptService->findServicesActifs();

        $this->render('RH::organisation/postes/create', [
            'departements' => $departements,
            'services'     => $services,
            'categories'   => PositionDTO::CATEGORIES,
            'niveaux'      => OrganizationModel::NIVEAUX,
            'old'          => [],
            'errors'       => [],
        ]);
    }

    public function store(): void
    {
        $this->requirePermission('organization.create');
        $this->verifyCsrf();

        $dto    = PositionDTO::fromRequest($_POST);
        $errors = $dto->validate();

        if ($errors !== []) {
            $this->render('RH::organisation/postes/create', [
                'departements' => $this->deptService->findDepartementsActifs(),
                'services'     => $this->deptService->findServicesActifs(),
                'categories'   => PositionDTO::CATEGORIES,
                'niveaux'      => OrganizationModel::NIVEAUX,
                'old'          => $_POST,
                'errors'       => $errors,
            ]);
            return;
        }

        try {
            $this->service->creer($dto, (int)$this->currentUser()['id']);
            Session::flash('success', 'Poste créé avec succès.');
            $this->redirect('/v2/rh/organisation/postes');
        } catch (\RuntimeException $e) {
            $this->render('RH::organisation/postes/create', [
                'departements' => $this->deptService->findDepartementsActifs(),
                'services'     => $this->deptService->findServicesActifs(),
                'categories'   => PositionDTO::CATEGORIES,
                'niveaux'      => OrganizationModel::NIVEAUX,
                'old'          => $_POST,
                'errors'       => ['global' => $e->getMessage()],
            ]);
        }
    }

    // ── Édition poste ─────────────────────────────────────────────────────────

    public function edit(int $id): void
    {
        $this->requirePermission('organization.update');

        try {
            $poste        = $this->service->findById($id);
            $departements = $this->deptService->findDepartementsActifs();
            $services     = $this->deptService->findServicesActifs($poste['departement_id'] ?? null);
        } catch (\RuntimeException $e) {
            Session::flash('error', $e->getMessage());
            $this->redirect('/v2/rh/organisation/postes');
            return;
        }

        $this->render('RH::organisation/postes/edit', [
            'poste'        => $poste,
            'departements' => $departements,
            'services'     => $services,
            'categories'   => PositionDTO::CATEGORIES,
            'niveaux'      => OrganizationModel::NIVEAUX,
            'old'          => [],
            'errors'       => [],
        ]);
    }

    public function update(int $id): void
    {
        $this->requirePermission('organization.update');
        $this->verifyCsrf();

        $dto    = PositionDTO::fromRequest($_POST);
        $errors = $dto->validate();

        if ($errors !== []) {
            try {
                $poste = $this->service->findById($id);
            } catch (\RuntimeException $e) {
                $poste = [];
            }
            $this->render('RH::organisation/postes/edit', [
                'poste'        => $poste,
                'departements' => $this->deptService->findDepartementsActifs(),
                'services'     => $this->deptService->findServicesActifs(),
                'categories'   => PositionDTO::CATEGORIES,
                'niveaux'      => OrganizationModel::NIVEAUX,
                'old'          => $_POST,
                'errors'       => $errors,
            ]);
            return;
        }

        try {
            $this->service->modifier($id, $dto, (int)$this->currentUser()['id']);
            Session::flash('success', 'Poste mis à jour.');
            $this->redirect('/v2/rh/organisation/postes');
        } catch (\RuntimeException $e) {
            Session::flash('error', $e->getMessage());
            $this->redirect('/v2/rh/organisation/postes/' . $id . '/edit');
        }
    }

    // ── Archivage / Restauration ──────────────────────────────────────────────

    public function archive(int $id): void
    {
        $this->requirePermission('organization.archive');
        $this->verifyCsrf();

        try {
            $this->service->archiver($id, (int)$this->currentUser()['id']);
            Session::flash('success', 'Poste archivé.');
        } catch (\RuntimeException $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect('/v2/rh/organisation/postes');
    }

    public function restore(int $id): void
    {
        $this->requirePermission('organization.archive');
        $this->verifyCsrf();

        try {
            $this->service->restaurer($id, (int)$this->currentUser()['id']);
            Session::flash('success', 'Poste restauré.');
        } catch (\RuntimeException $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect('/v2/rh/organisation/postes');
    }

    // ── Fonctions ─────────────────────────────────────────────────────────────

    public function fonctions(): void
    {
        $this->requirePermission('organization.view');

        $fonctions = $this->service->findAllFonctions();

        $this->render('RH::organisation/fonctions/index', [
            'fonctions' => $fonctions,
            'canCreate' => $this->policy->canCreate($this->currentUser()),
            'canUpdate' => $this->policy->canUpdate($this->currentUser()),
            'canArchive'=> $this->policy->canArchive($this->currentUser()),
        ]);
    }

    public function storeFonction(): void
    {
        $this->requirePermission('organization.create');
        $this->verifyCsrf();

        try {
            $this->service->creerFonction($_POST, (int)$this->currentUser()['id']);
            Session::flash('success', 'Fonction créée avec succès.');
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect('/v2/rh/organisation/fonctions');
    }

    public function updateFonction(int $id): void
    {
        $this->requirePermission('organization.update');
        $this->verifyCsrf();

        try {
            $this->service->modifierFonction($id, $_POST, (int)$this->currentUser()['id']);
            Session::flash('success', 'Fonction mise à jour.');
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect('/v2/rh/organisation/fonctions');
    }

    public function archiveFonction(int $id): void
    {
        $this->requirePermission('organization.archive');
        $this->verifyCsrf();

        try {
            $this->service->archiverFonction($id, (int)$this->currentUser()['id']);
            Session::flash('success', 'Fonction archivée.');
        } catch (\RuntimeException $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect('/v2/rh/organisation/fonctions');
    }
}

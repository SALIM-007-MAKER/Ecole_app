<?php

declare(strict_types=1);

namespace App\Modules\RH\Organisation\Controllers;

use App\Modules\RH\Organisation\DTO\DepartmentDTO;
use App\Modules\RH\Organisation\DTO\ServiceDTO;
use App\Modules\RH\Organisation\DTO\OrganizationFiltersDTO;
use App\Modules\RH\Organisation\Policies\OrganizationPolicy;
use App\Modules\RH\Organisation\Services\DepartmentService;
use App\Modules\RH\Organisation\Services\OrganizationService;
use Core\Controller;
use Core\Session;

class DepartmentController extends Controller
{
    private DepartmentService  $service;
    private OrganizationPolicy $policy;
    private OrganizationService $orgService;

    public function __construct()
    {
        parent::__construct();
        $this->service    = new DepartmentService();
        $this->policy     = new OrganizationPolicy();
        $this->orgService = new OrganizationService();
    }

    // ── Liste ─────────────────────────────────────────────────────────────────

    public function index(): void
    {
        $this->requirePermission('organization.view');

        $filters = OrganizationFiltersDTO::fromRequest($_GET);
        $result  = $this->service->paginate($filters);

        $this->render('RH::organisation/departements/index', [
            'departements' => $result['items'],
            'pagination'   => $result,
            'filters'      => $filters,
            'canCreate'    => $this->policy->canCreate($this->user),
            'canUpdate'    => $this->policy->canUpdate($this->user),
            'canArchive'   => $this->policy->canArchive($this->user),
            'canExport'    => $this->policy->canExport($this->user),
        ]);
    }

    // ── Détail ────────────────────────────────────────────────────────────────

    public function show(int $id): void
    {
        $this->requirePermission('organization.view');

        try {
            $dept     = $this->service->findByIdWithArchived($id);
            $services = $this->service->findServicesByDept($id);
            $historique = $this->orgService->historique('departement', $id);
        } catch (\RuntimeException $e) {
            Session::flash('error', $e->getMessage());
            $this->redirect('/v2/rh/organisation/departements');
            return;
        }

        $this->render('RH::organisation/departements/show', [
            'dept'      => $dept,
            'services'  => $services,
            'historique'=> $historique,
            'canUpdate' => $this->policy->canUpdate($this->user),
            'canArchive'=> $this->policy->canArchive($this->user),
        ]);
    }

    // ── Création département ──────────────────────────────────────────────────

    public function create(): void
    {
        $this->requirePermission('organization.create');

        $parents = $this->service->findDepartementsActifs();

        $this->render('RH::organisation/departements/create', [
            'parents' => $parents,
            'old'     => [],
            'errors'  => [],
        ]);
    }

    public function store(): void
    {
        $this->requirePermission('organization.create');
        $this->verifyCsrf();

        $dto    = DepartmentDTO::fromRequest($_POST);
        $errors = $dto->validate();

        if ($errors !== []) {
            $this->render('RH::organisation/departements/create', [
                'parents' => $this->service->findDepartementsActifs(),
                'old'     => $_POST,
                'errors'  => $errors,
            ]);
            return;
        }

        try {
            $id = $this->service->creer($dto, (int)$this->user['id']);
            Session::flash('success', 'Département créé avec succès.');
            $this->redirect('/v2/rh/organisation/departements/' . $id);
        } catch (\RuntimeException $e) {
            $this->render('RH::organisation/departements/create', [
                'parents' => $this->service->findDepartementsActifs(),
                'old'     => $_POST,
                'errors'  => ['global' => $e->getMessage()],
            ]);
        }
    }

    // ── Édition département ───────────────────────────────────────────────────

    public function edit(int $id): void
    {
        $this->requirePermission('organization.update');

        try {
            $dept    = $this->service->findById($id);
            $parents = array_filter(
                $this->service->findDepartementsActifs(),
                fn($p) => (int)$p['id'] !== $id
            );
        } catch (\RuntimeException $e) {
            Session::flash('error', $e->getMessage());
            $this->redirect('/v2/rh/organisation/departements');
            return;
        }

        $this->render('RH::organisation/departements/edit', [
            'dept'    => $dept,
            'parents' => array_values($parents),
            'old'     => [],
            'errors'  => [],
        ]);
    }

    public function update(int $id): void
    {
        $this->requirePermission('organization.update');
        $this->verifyCsrf();

        $dto    = DepartmentDTO::fromRequest($_POST);
        $errors = $dto->validate();

        if ($errors !== []) {
            try {
                $dept    = $this->service->findById($id);
                $parents = array_filter(
                    $this->service->findDepartementsActifs(),
                    fn($p) => (int)$p['id'] !== $id
                );
            } catch (\RuntimeException $e) {
                $dept = []; $parents = [];
            }
            $this->render('RH::organisation/departements/edit', [
                'dept'    => $dept,
                'parents' => array_values($parents),
                'old'     => $_POST,
                'errors'  => $errors,
            ]);
            return;
        }

        try {
            $this->service->modifier($id, $dto, (int)$this->user['id']);
            Session::flash('success', 'Département mis à jour.');
            $this->redirect('/v2/rh/organisation/departements/' . $id);
        } catch (\RuntimeException $e) {
            Session::flash('error', $e->getMessage());
            $this->redirect('/v2/rh/organisation/departements/' . $id . '/edit');
        }
    }

    // ── Archivage / Restauration ──────────────────────────────────────────────

    public function archive(int $id): void
    {
        $this->requirePermission('organization.archive');
        $this->verifyCsrf();

        try {
            $this->service->archiver($id, (int)$this->user['id']);
            Session::flash('success', 'Département archivé.');
        } catch (\RuntimeException $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect('/v2/rh/organisation/departements');
    }

    public function restore(int $id): void
    {
        $this->requirePermission('organization.archive');
        $this->verifyCsrf();

        try {
            $this->service->restaurer($id, (int)$this->user['id']);
            Session::flash('success', 'Département restauré.');
        } catch (\RuntimeException $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect('/v2/rh/organisation/departements');
    }

    // ── Services d'un département ─────────────────────────────────────────────

    public function storeService(int $deptId): void
    {
        $this->requirePermission('organization.create');
        $this->verifyCsrf();

        $_POST['departement_id'] = $deptId;
        $dto    = ServiceDTO::fromRequest($_POST);
        $errors = $dto->validate();

        if ($errors !== []) {
            Session::flash('error', implode(' ', $errors));
            $this->redirect('/v2/rh/organisation/departements/' . $deptId);
            return;
        }

        try {
            $this->service->creerService($dto, (int)$this->user['id']);
            Session::flash('success', 'Service ajouté au département.');
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect('/v2/rh/organisation/departements/' . $deptId);
    }

    public function archiveService(int $deptId, int $serviceId): void
    {
        $this->requirePermission('organization.archive');
        $this->verifyCsrf();

        try {
            $this->service->archiverService($serviceId, (int)$this->user['id']);
            Session::flash('success', 'Service archivé.');
        } catch (\RuntimeException $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect('/v2/rh/organisation/departements/' . $deptId);
    }
}

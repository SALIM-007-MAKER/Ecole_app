<?php

namespace App\Modules\RH\Employes\Controllers;

use App\Modules\RH\Employes\DTO\EmployeeDTO;
use App\Modules\RH\Employes\DTO\EmployeeFiltersDTO;
use App\Modules\RH\Employes\Policies\EmployeePolicy;
use App\Modules\RH\Employes\Services\EmployeeService;
use Core\Controller;
use Core\Session;

class EmployeeController extends Controller
{
    private EmployeeService $service;
    private EmployeePolicy  $policy;

    public function __construct()
    {
        parent::__construct();
        $this->service = new EmployeeService();
        $this->policy  = new EmployeePolicy();
    }

    // ── Liste ─────────────────────────────────────────────────────────────────

    public function index(): void
    {
        $this->requirePermission('employee.view');

        $filters  = EmployeeFiltersDTO::fromRequest($_GET);
        $result   = $this->service->paginate($filters);
        $stats    = $this->service->statistiques();
        $departements = $this->service->departements();

        $this->render('RH::employes/index', [
            'employes'    => $result['data'],
            'pagination'  => $result,
            'stats'       => $stats,
            'departements'=> $departements,
            'filters'     => $filters,
            'canCreate'   => $this->policy->canCreate($this->user),
            'canExport'   => $this->policy->canExport($this->user),
        ]);
    }

    // ── Détail ────────────────────────────────────────────────────────────────

    public function show(int $id): void
    {
        $this->requirePermission('employee.view');

        $employe = $this->service->findById($id);
        if ($employe === null) {
            Session::flash('error', 'Employé introuvable.');
            $this->redirect('/v2/rh/employes');
            return;
        }

        $contacts = $this->service->contactsUrgence($id);

        $this->render('RH::employes/show', [
            'employe'  => $employe,
            'contacts' => $contacts,
            'canUpdate'  => $this->policy->canUpdate($this->user),
            'canArchive' => $this->policy->canArchive($this->user),
            'canRestore' => $this->policy->canRestore($this->user),
        ]);
    }

    // ── Formulaire création ───────────────────────────────────────────────────

    public function create(): void
    {
        $this->requirePermission('employee.create');

        $this->render('RH::employes/create', [
            'departements' => $this->service->departements(),
            'postes'       => $this->service->postes(),
            'users'        => $this->service->users(),
            'types'        => EmployeeDTO::TYPES,
            'statuts'      => EmployeeDTO::STATUTS,
            'genres'       => EmployeeDTO::GENRES,
        ]);
    }

    // ── Enregistrement création ───────────────────────────────────────────────

    public function store(): void
    {
        $this->requirePermission('employee.create');
        $this->verifyCsrf();

        $dto      = EmployeeDTO::fromRequest($_POST);
        $contacts = $_POST['contacts'] ?? [];
        $errors   = $dto->validate();

        if (!empty($errors)) {
            Session::flash('errors', $errors);
            Session::flash('old', $_POST);
            $this->redirect('/v2/rh/employes/create');
            return;
        }

        try {
            $id = $this->service->creer($dto, $contacts, (int)$this->user['id']);
            Session::flash('success', 'Employé créé avec succès.');
            $this->redirect("/v2/rh/employes/{$id}");
        } catch (\InvalidArgumentException $e) {
            Session::flash('error', $e->getMessage());
            Session::flash('old', $_POST);
            $this->redirect('/v2/rh/employes/create');
        } catch (\RuntimeException $e) {
            Session::flash('error', $e->getMessage());
            Session::flash('old', $_POST);
            $this->redirect('/v2/rh/employes/create');
        }
    }

    // ── Formulaire édition ────────────────────────────────────────────────────

    public function edit(int $id): void
    {
        $this->requirePermission('employee.update');

        $employe = $this->service->findById($id);
        if ($employe === null) {
            Session::flash('error', 'Employé introuvable.');
            $this->redirect('/v2/rh/employes');
            return;
        }

        $contacts = $this->service->contactsUrgence($id);

        $this->render('RH::employes/edit', [
            'employe'      => $employe,
            'contacts'     => $contacts,
            'departements' => $this->service->departements(),
            'postes'       => $this->service->postes(),
            'users'        => $this->service->users(),
            'types'        => EmployeeDTO::TYPES,
            'statuts'      => EmployeeDTO::STATUTS,
            'genres'       => EmployeeDTO::GENRES,
        ]);
    }

    // ── Enregistrement modification ───────────────────────────────────────────

    public function update(int $id): void
    {
        $this->requirePermission('employee.update');
        $this->verifyCsrf();

        $employe = $this->service->findById($id);
        if ($employe === null) {
            Session::flash('error', 'Employé introuvable.');
            $this->redirect('/v2/rh/employes');
            return;
        }

        $dto      = EmployeeDTO::fromRequest($_POST);
        $contacts = $_POST['contacts'] ?? [];
        $errors   = $dto->validate();

        if (!empty($errors)) {
            Session::flash('errors', $errors);
            Session::flash('old', $_POST);
            $this->redirect("/v2/rh/employes/{$id}/edit");
            return;
        }

        try {
            $this->service->modifier($id, $dto, $contacts, (int)$this->user['id']);
            Session::flash('success', 'Employé mis à jour avec succès.');
            $this->redirect("/v2/rh/employes/{$id}");
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            Session::flash('error', $e->getMessage());
            Session::flash('old', $_POST);
            $this->redirect("/v2/rh/employes/{$id}/edit");
        }
    }

    // ── Archivage ─────────────────────────────────────────────────────────────

    public function archive(int $id): void
    {
        $this->requirePermission('employee.archive');
        $this->verifyCsrf();

        try {
            $this->service->archiver($id, (int)$this->user['id']);
            Session::flash('success', 'Employé archivé.');
        } catch (\RuntimeException $e) {
            Session::flash('error', $e->getMessage());
        }

        $this->redirect('/v2/rh/employes');
    }

    // ── Restauration ─────────────────────────────────────────────────────────

    public function restore(int $id): void
    {
        $this->requirePermission('employee.restore');
        $this->verifyCsrf();

        try {
            $this->service->restaurer($id, (int)$this->user['id']);
            Session::flash('success', 'Employé restauré.');
            $this->redirect("/v2/rh/employes/{$id}");
        } catch (\RuntimeException $e) {
            Session::flash('error', $e->getMessage());
            $this->redirect('/v2/rh/employes');
        }
    }

    // ── Changement de statut ──────────────────────────────────────────────────

    public function changerStatut(int $id): void
    {
        $this->requirePermission('employee.update');
        $this->verifyCsrf();

        $statut = trim($_POST['statut'] ?? '');

        try {
            $this->service->changerStatut($id, $statut, (int)$this->user['id']);
            Session::flash('success', 'Statut mis à jour.');
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            Session::flash('error', $e->getMessage());
        }

        $this->redirect("/v2/rh/employes/{$id}");
    }

    // ── Statistiques ─────────────────────────────────────────────────────────

    public function statistiques(): void
    {
        $this->requirePermission('employee.view');

        $stats = $this->service->statistiques();

        $this->render('RH::employes/statistiques', [
            'stats' => $stats,
        ]);
    }

    // ── Export CSV ────────────────────────────────────────────────────────────

    public function export(): void
    {
        $this->requirePermission('employee.export');

        $filters = EmployeeFiltersDTO::fromRequest($_GET);
        $csv     = $this->service->exporterCsv($filters);

        $filename = 'employes_' . date('Ymd_His') . '.csv';
        header('Content-Type: text/csv; charset=UTF-8');
        header("Content-Disposition: attachment; filename=\"{$filename}\"");
        header('Cache-Control: no-cache, no-store, must-revalidate');
        echo $csv;
        exit;
    }
}

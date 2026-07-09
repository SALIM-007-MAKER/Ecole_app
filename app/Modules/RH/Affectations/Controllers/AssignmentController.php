<?php

declare(strict_types=1);

namespace App\Modules\RH\Affectations\Controllers;

use App\Modules\RH\Affectations\DTO\AssignmentDTO;
use App\Modules\RH\Affectations\DTO\AssignmentFiltersDTO;
use App\Modules\RH\Affectations\DTO\MatiereAssignmentDTO;
use App\Modules\RH\Affectations\Models\AssignmentModel;
use App\Modules\RH\Affectations\Policies\AssignmentPolicy;
use App\Modules\RH\Affectations\Services\AssignmentService;
use Core\Controller;
use Core\Session;

class AssignmentController extends Controller
{
    private AssignmentService $service;
    private AssignmentPolicy  $policy;

    public function __construct()
    {
        parent::__construct();
        $this->service = new AssignmentService();
        $this->policy  = new AssignmentPolicy();
    }

    // ── Liste ─────────────────────────────────────────────────────────────────

    public function index(): void
    {
        $this->requirePermission('assignment.view');

        $filters = AssignmentFiltersDTO::fromRequest($_GET);
        $result  = $this->service->paginate($filters);
        $stats   = $this->service->statistiques();
        $refs    = $this->service->referentiels();

        $this->render('RH::affectations/index', [
            'affectations' => $result['items'],
            'pagination'   => $result,
            'filters'      => $filters,
            'stats'        => $stats,
            'departements' => $refs['departements'],
            'postes'       => $refs['postes'],
            'model'        => AssignmentModel::class,
            'canCreate'    => $this->policy->canCreate($this->user),
            'canExport'    => $this->policy->canExport($this->user),
        ]);
    }

    // ── Détail ────────────────────────────────────────────────────────────────

    public function show(int $id): void
    {
        $this->requirePermission('assignment.view');

        try {
            $affectation = $this->service->findById($id);
            $matieres    = $this->service->findMatieres($id);
            $historique  = $this->service->findHistorique($id);
        } catch (\RuntimeException $e) {
            Session::flash('error', $e->getMessage());
            $this->redirect('/v2/rh/affectations');
            return;
        }

        $this->render('RH::affectations/show', [
            'affectation' => $affectation,
            'matieres'    => $matieres,
            'historique'  => $historique,
            'model'       => AssignmentModel::class,
            'canUpdate'   => $this->policy->canUpdate($this->user),
            'canArchive'  => $this->policy->canArchive($this->user),
        ]);
    }

    // ── Création ──────────────────────────────────────────────────────────────

    public function create(): void
    {
        $this->requirePermission('assignment.create');

        $employeId = ($_GET['employe_id'] ?? '') !== '' ? (int)$_GET['employe_id'] : null;
        $refs      = $this->service->referentiels($employeId);

        $this->render('RH::affectations/create', [
            'refs'        => $refs,
            'model'       => AssignmentModel::class,
            'preEmployeId'=> $employeId,
            'old'         => [],
            'errors'      => [],
        ]);
    }

    public function store(): void
    {
        $this->requirePermission('assignment.create');
        $this->verifyCsrf();

        $dto    = AssignmentDTO::fromRequest($_POST);
        $errors = $dto->validate();

        if ($errors !== []) {
            $refs = $this->service->referentiels($dto->employeId ?: null);
            $this->render('RH::affectations/create', [
                'refs'        => $refs,
                'model'       => AssignmentModel::class,
                'preEmployeId'=> $dto->employeId ?: null,
                'old'         => $_POST,
                'errors'      => $errors,
            ]);
            return;
        }

        try {
            $userName = trim(($this->user['prenom'] ?? '') . ' ' . ($this->user['nom'] ?? '')) ?: 'Système';
            $id = $this->service->creer($dto, (int)$this->user['id'], $userName);
            Session::flash('success', 'Affectation créée avec succès.');
            $this->redirect('/v2/rh/affectations/' . $id);
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            $refs = $this->service->referentiels($dto->employeId ?: null);
            $this->render('RH::affectations/create', [
                'refs'        => $refs,
                'model'       => AssignmentModel::class,
                'preEmployeId'=> $dto->employeId ?: null,
                'old'         => $_POST,
                'errors'      => ['global' => $e->getMessage()],
            ]);
        }
    }

    // ── Édition ───────────────────────────────────────────────────────────────

    public function edit(int $id): void
    {
        $this->requirePermission('assignment.update');

        try {
            $affectation = $this->service->findById($id);
            $refs        = $this->service->referentiels((int)$affectation['employe_id']);
        } catch (\RuntimeException $e) {
            Session::flash('error', $e->getMessage());
            $this->redirect('/v2/rh/affectations');
            return;
        }

        $this->render('RH::affectations/edit', [
            'affectation' => $affectation,
            'refs'        => $refs,
            'model'       => AssignmentModel::class,
            'old'         => [],
            'errors'      => [],
        ]);
    }

    public function update(int $id): void
    {
        $this->requirePermission('assignment.update');
        $this->verifyCsrf();

        $dto    = AssignmentDTO::fromRequest($_POST);
        $errors = $dto->validate();

        if ($errors !== []) {
            try {
                $affectation = $this->service->findById($id);
                $refs        = $this->service->referentiels((int)$affectation['employe_id']);
            } catch (\RuntimeException) {
                $affectation = []; $refs = [];
            }
            $this->render('RH::affectations/edit', [
                'affectation' => $affectation,
                'refs'        => $refs,
                'model'       => AssignmentModel::class,
                'old'         => $_POST,
                'errors'      => $errors,
            ]);
            return;
        }

        try {
            $userName = trim(($this->user['prenom'] ?? '') . ' ' . ($this->user['nom'] ?? '')) ?: 'Système';
            $this->service->modifier($id, $dto, (int)$this->user['id'], $userName);
            Session::flash('success', 'Affectation mise à jour.');
            $this->redirect('/v2/rh/affectations/' . $id);
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            Session::flash('error', $e->getMessage());
            $this->redirect('/v2/rh/affectations/' . $id . '/edit');
        }
    }

    // ── Transfert ─────────────────────────────────────────────────────────────

    public function transferer(int $id): void
    {
        $this->requirePermission('assignment.update');
        $this->verifyCsrf();

        $motif = trim($_POST['motif'] ?? '');
        $dest  = [
            'poste_id'       => ($_POST['poste_id']       ?? '') !== '' ? (int)$_POST['poste_id']       : null,
            'departement_id' => ($_POST['departement_id'] ?? '') !== '' ? (int)$_POST['departement_id'] : null,
            'service_id'     => ($_POST['service_id']     ?? '') !== '' ? (int)$_POST['service_id']     : null,
            'responsable_id' => ($_POST['responsable_id'] ?? '') !== '' ? (int)$_POST['responsable_id'] : null,
        ];

        try {
            $userName = trim(($this->user['prenom'] ?? '') . ' ' . ($this->user['nom'] ?? '')) ?: 'Système';
            $this->service->transferer($id, $dest, $motif, (int)$this->user['id'], $userName);
            Session::flash('success', 'Transfert enregistré.');
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect('/v2/rh/affectations/' . $id);
    }

    // ── Suspension / Réactivation ─────────────────────────────────────────────

    public function suspendre(int $id): void
    {
        $this->requirePermission('assignment.update');
        $this->verifyCsrf();

        try {
            $userName = trim(($this->user['prenom'] ?? '') . ' ' . ($this->user['nom'] ?? '')) ?: 'Système';
            $this->service->suspendre($id, (int)$this->user['id'], $userName);
            Session::flash('success', 'Affectation suspendue.');
        } catch (\RuntimeException $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect('/v2/rh/affectations/' . $id);
    }

    public function reactiver(int $id): void
    {
        $this->requirePermission('assignment.update');
        $this->verifyCsrf();

        try {
            $userName = trim(($this->user['prenom'] ?? '') . ' ' . ($this->user['nom'] ?? '')) ?: 'Système';
            $this->service->reactiver($id, (int)$this->user['id'], $userName);
            Session::flash('success', 'Affectation réactivée.');
        } catch (\RuntimeException $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect('/v2/rh/affectations/' . $id);
    }

    // ── Clôture ───────────────────────────────────────────────────────────────

    public function clore(int $id): void
    {
        $this->requirePermission('assignment.update');
        $this->verifyCsrf();

        $motif = trim($_POST['motif'] ?? '');

        try {
            $userName = trim(($this->user['prenom'] ?? '') . ' ' . ($this->user['nom'] ?? '')) ?: 'Système';
            $this->service->clore($id, $motif, (int)$this->user['id'], $userName);
            Session::flash('success', 'Affectation clôturée.');
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect('/v2/rh/affectations/' . $id);
    }

    // ── Archivage ─────────────────────────────────────────────────────────────

    public function archive(int $id): void
    {
        $this->requirePermission('assignment.archive');
        $this->verifyCsrf();

        $motif = trim($_POST['motif'] ?? 'Archivage manuel');

        try {
            $userName = trim(($this->user['prenom'] ?? '') . ' ' . ($this->user['nom'] ?? '')) ?: 'Système';
            $this->service->archiver($id, $motif, (int)$this->user['id'], $userName);
            Session::flash('success', 'Affectation archivée.');
        } catch (\RuntimeException $e) {
            Session::flash('error', $e->getMessage());
            $this->redirect('/v2/rh/affectations/' . $id);
            return;
        }
        $this->redirect('/v2/rh/affectations');
    }

    // ── Matières ─────────────────────────────────────────────────────────────

    public function storeMatiereAssignment(int $id): void
    {
        $this->requirePermission('assignment.update');
        $this->verifyCsrf();

        $dto = MatiereAssignmentDTO::fromRequest($_POST);

        try {
            $this->service->ajouterMatiere($id, $dto, (int)$this->user['id']);
            Session::flash('success', 'Affectation matière ajoutée.');
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect('/v2/rh/affectations/' . $id);
    }

    public function removeMatiereAssignment(int $id, int $matId): void
    {
        $this->requirePermission('assignment.update');
        $this->verifyCsrf();

        try {
            $this->service->retirerMatiere($id, $matId, (int)$this->user['id']);
            Session::flash('success', 'Affectation matière clôturée.');
        } catch (\RuntimeException $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect('/v2/rh/affectations/' . $id);
    }

    // ── Statistiques ──────────────────────────────────────────────────────────

    public function statistiques(): void
    {
        $this->requirePermission('assignment.view');

        $stats = $this->service->statistiques();

        $this->render('RH::affectations/statistiques', [
            'stats' => $stats,
            'model' => AssignmentModel::class,
        ]);
    }

    // ── Export ────────────────────────────────────────────────────────────────

    public function export(): void
    {
        $this->requirePermission('assignment.export');

        $filters = AssignmentFiltersDTO::fromRequest($_GET);
        $csv     = $this->service->exporterCsv($filters);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="affectations_' . date('Ymd') . '.csv"');
        echo $csv;
        exit;
    }
}

<?php

namespace App\Modules\RH\Enseignants\Controllers;

use App\Modules\RH\Enseignants\DTO\QualificationDTO;
use App\Modules\RH\Enseignants\DTO\TeacherDTO;
use App\Modules\RH\Enseignants\DTO\TeacherFiltersDTO;
use App\Modules\RH\Enseignants\Policies\TeacherPolicy;
use App\Modules\RH\Enseignants\Services\TeacherService;
use Core\Controller;
use Core\Session;

class TeacherController extends Controller
{
    private TeacherService $service;
    private TeacherPolicy  $policy;

    public function __construct()
    {
        parent::__construct();
        $this->service = new TeacherService();
        $this->policy  = new TeacherPolicy();
    }

    // ── Liste ─────────────────────────────────────────────────────────────────

    public function index(): void
    {
        $this->requirePermission('teacher.view');

        $filters  = TeacherFiltersDTO::fromRequest($_GET);
        $result   = $this->service->paginate($filters);
        $stats    = $this->service->statistiques();
        $matieres = $this->service->matieresPourSelect();

        $this->render('RH::enseignants/index', [
            'enseignants' => $result['data'],
            'pagination'  => $result,
            'stats'       => $stats,
            'matieres'    => $matieres,
            'filters'     => $filters,
            'canCreate'   => $this->policy->canCreate($this->currentUser()),
            'canExport'   => $this->policy->canExport($this->currentUser()),
        ]);
    }

    // ── Détail ────────────────────────────────────────────────────────────────

    public function show(int $id): void
    {
        $this->requirePermission('teacher.view');

        $enseignant = $this->service->findById($id);
        if ($enseignant === null) {
            Session::flash('error', 'Profil enseignant introuvable.');
            $this->redirect('/v2/rh/enseignants');
            return;
        }

        $this->render('RH::enseignants/show', [
            'enseignant'     => $enseignant,
            'matieres'       => $this->service->matieres($id),
            'qualifications' => $this->service->qualifications($id),
            'canUpdate'      => $this->policy->canUpdate($this->currentUser()),
            'canAssign'      => $this->policy->canAssign($this->currentUser()),
        ]);
    }

    // ── Formulaire création ───────────────────────────────────────────────────

    public function create(): void
    {
        $this->requirePermission('teacher.create');

        $this->render('RH::enseignants/create', [
            'employes' => $this->service->employesSansProfile(),
            'statuts'  => TeacherDTO::STATUTS_PEDAGOGIQUES,
            'niveaux'  => TeacherDTO::NIVEAUX_DISPONIBLES,
            'matieres' => $this->service->matieresPourSelect(),
        ]);
    }

    // ── Enregistrement création ───────────────────────────────────────────────

    public function store(): void
    {
        $this->requirePermission('teacher.create');
        $this->verifyCsrf();

        $dto    = TeacherDTO::fromRequest($_POST);
        $errors = $dto->validate();

        if (!empty($errors)) {
            Session::flash('errors', $errors);
            Session::flash('old', $_POST);
            $this->redirect('/v2/rh/enseignants/create');
            return;
        }

        try {
            $id = $this->service->creer($dto, (int)$this->currentUser()['id']);

            // Affectation matières si fournies
            $matieres = $_POST['matieres'] ?? [];
            if (!empty($matieres)) {
                $this->service->assignerMatieres($id, $matieres, (int)$this->currentUser()['id']);
            }

            Session::flash('success', 'Profil enseignant créé avec succès.');
            $this->redirect("/v2/rh/enseignants/{$id}");
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            Session::flash('error', $e->getMessage());
            Session::flash('old', $_POST);
            $this->redirect('/v2/rh/enseignants/create');
        }
    }

    // ── Formulaire édition ────────────────────────────────────────────────────

    public function edit(int $id): void
    {
        $this->requirePermission('teacher.update');

        $enseignant = $this->service->findById($id);
        if ($enseignant === null) {
            Session::flash('error', 'Profil enseignant introuvable.');
            $this->redirect('/v2/rh/enseignants');
            return;
        }

        $this->render('RH::enseignants/edit', [
            'enseignant' => $enseignant,
            'matieres'   => $this->service->matieresPourSelect(),
            'assigned'   => $this->service->matieres($id),
            'statuts'    => TeacherDTO::STATUTS_PEDAGOGIQUES,
            'niveaux'    => TeacherDTO::NIVEAUX_DISPONIBLES,
        ]);
    }

    // ── Enregistrement modification ───────────────────────────────────────────

    public function update(int $id): void
    {
        $this->requirePermission('teacher.update');
        $this->verifyCsrf();

        $enseignant = $this->service->findById($id);
        if ($enseignant === null) {
            Session::flash('error', 'Profil enseignant introuvable.');
            $this->redirect('/v2/rh/enseignants');
            return;
        }

        // On réinjecte employe_id car il est immuable mais requis par DTO
        $_POST['employe_id'] = $enseignant['employe_id'];
        $dto    = TeacherDTO::fromRequest($_POST);
        $errors = $dto->validate();

        if (!empty($errors)) {
            Session::flash('errors', $errors);
            Session::flash('old', $_POST);
            $this->redirect("/v2/rh/enseignants/{$id}/edit");
            return;
        }

        try {
            $this->service->modifier($id, $dto, (int)$this->currentUser()['id']);
            Session::flash('success', 'Profil enseignant mis à jour.');
            $this->redirect("/v2/rh/enseignants/{$id}");
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            Session::flash('error', $e->getMessage());
            Session::flash('old', $_POST);
            $this->redirect("/v2/rh/enseignants/{$id}/edit");
        }
    }

    // ── Affectation matières ──────────────────────────────────────────────────

    public function assignerMatieres(int $id): void
    {
        $this->requirePermission('teacher.assign');
        $this->verifyCsrf();

        $matieres = $_POST['matieres'] ?? [];

        try {
            $this->service->assignerMatieres($id, $matieres, (int)$this->currentUser()['id']);
            Session::flash('success', 'Habilitations matières mises à jour.');
        } catch (\RuntimeException $e) {
            Session::flash('error', $e->getMessage());
        }

        $this->redirect("/v2/rh/enseignants/{$id}");
    }

    // ── Qualifications ────────────────────────────────────────────────────────

    public function ajouterQualification(int $id): void
    {
        $this->requirePermission('teacher.update');
        $this->verifyCsrf();

        $dto    = QualificationDTO::fromRequest($_POST);
        $errors = $dto->validate();

        if (!empty($errors)) {
            Session::flash('errors', $errors);
            $this->redirect("/v2/rh/enseignants/{$id}#qualifications");
            return;
        }

        try {
            $this->service->ajouterQualification($id, $dto, (int)$this->currentUser()['id']);
            Session::flash('success', 'Qualification ajoutée.');
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            Session::flash('error', $e->getMessage());
        }

        $this->redirect("/v2/rh/enseignants/{$id}#qualifications");
    }

    public function supprimerQualification(int $id, int $qualId): void
    {
        $this->requirePermission('teacher.update');
        $this->verifyCsrf();

        try {
            $this->service->supprimerQualification($id, $qualId, (int)$this->currentUser()['id']);
            Session::flash('success', 'Qualification supprimée.');
        } catch (\RuntimeException $e) {
            Session::flash('error', $e->getMessage());
        }

        $this->redirect("/v2/rh/enseignants/{$id}#qualifications");
    }

    // ── Archivage ─────────────────────────────────────────────────────────────

    public function archive(int $id): void
    {
        $this->requirePermission('teacher.update');
        $this->verifyCsrf();

        try {
            $this->service->archiver($id, (int)$this->currentUser()['id']);
            Session::flash('success', 'Profil enseignant archivé.');
        } catch (\RuntimeException $e) {
            Session::flash('error', $e->getMessage());
        }

        $this->redirect('/v2/rh/enseignants');
    }

    // ── Restauration ─────────────────────────────────────────────────────────

    public function restore(int $id): void
    {
        $this->requirePermission('teacher.update');
        $this->verifyCsrf();

        try {
            $this->service->restaurer($id, (int)$this->currentUser()['id']);
            Session::flash('success', 'Profil enseignant restauré.');
            $this->redirect("/v2/rh/enseignants/{$id}");
        } catch (\RuntimeException $e) {
            Session::flash('error', $e->getMessage());
            $this->redirect('/v2/rh/enseignants');
        }
    }

    // ── Statistiques ─────────────────────────────────────────────────────────

    public function statistiques(): void
    {
        $this->requirePermission('teacher.view');

        $this->render('RH::enseignants/statistiques', [
            'stats' => $this->service->statistiques(),
        ]);
    }

    // ── Export CSV ────────────────────────────────────────────────────────────

    public function export(): void
    {
        $this->requirePermission('teacher.export');

        $filters  = TeacherFiltersDTO::fromRequest($_GET);
        $csv      = $this->service->exporterCsv($filters);
        $filename = 'enseignants_' . date('Ymd_His') . '.csv';

        header('Content-Type: text/csv; charset=UTF-8');
        header("Content-Disposition: attachment; filename=\"{$filename}\"");
        header('Cache-Control: no-cache, no-store, must-revalidate');
        echo $csv;
        exit;
    }
}

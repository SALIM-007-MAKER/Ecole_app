<?php

namespace App\Modules\VieScolaire\Absences\Controllers;

use App\Models\ClasseModel;
use App\Modules\VieScolaire\Absences\DTO\AbsenceDTO;
use App\Modules\VieScolaire\Absences\DTO\AbsenceFiltersDTO;
use App\Modules\VieScolaire\Absences\DTO\JustificationDTO;
use App\Modules\VieScolaire\Absences\Policies\AbsencePolicy;
use App\Modules\VieScolaire\Absences\Repositories\AbsenceRepository;
use App\Modules\VieScolaire\Absences\Services\AbsenceService;
use App\Shared\Auth\EleveScopeTrait;
use Core\Controller;
use Core\Session;

class AbsenceController extends Controller
{
    use EleveScopeTrait;

    private AbsenceRepository $repo;
    private AbsenceService    $service;
    private AbsencePolicy     $policy;

    public function __construct()
    {
        parent::__construct();
        $this->repo    = new AbsenceRepository();
        $this->service = new AbsenceService();
        $this->policy  = new AbsencePolicy();
    }

    // ── Liste ─────────────────────────────────────────────────────────────────

    public function index(): void
    {
        $this->requirePermission('attendance.view');

        $user    = $this->currentUser();
        $filters = AbsenceFiltersDTO::fromRequest($_GET);

        $scope = $this->myEleveIds();
        if ($scope !== null) {
            $filters = $filters->withEleveIds($scope);
        }

        $result  = $this->service->paginate($filters);
        $classes = (new ClasseModel())->findForSelect();

        $this->render('VieScolaire::absences/index', [
            'title'      => 'Absences',
            'result'     => $result,
            'filters'    => $filters,
            'classes'    => $classes,
            'perms'      => $user['permissions'] ?? [],
        ]);
    }

    // ── Fiche ─────────────────────────────────────────────────────────────────

    public function show(string $id): void
    {
        $this->requirePermission('attendance.view');

        $absence = $this->service->findById((int)$id);
        if (!$absence) {
            Session::flash('error', 'Absence introuvable.');
            $this->redirect(BASE_URL . '/v2/vie-scolaire/absences');
        }
        $this->assertOwnEleve((int)$absence['eleve_id']);

        $justification = $this->repo->findJustificationByAbsence((int)$id);
        $user          = $this->currentUser();

        $this->render('VieScolaire::absences/show', [
            'title'         => 'Absence — ' . $absence['eleve_prenom'] . ' ' . $absence['eleve_nom'],
            'absence'       => $absence,
            'justification' => $justification,
            'perms'         => $user['permissions'] ?? [],
        ]);
    }

    // ── Création ──────────────────────────────────────────────────────────────

    public function create(): void
    {
        $this->requirePermission('attendance.create');

        $classes = (new ClasseModel())->findForSelect();
        $motifs  = $this->service->motifs();

        $this->render('VieScolaire::absences/create', [
            'title'   => 'Enregistrer une absence',
            'classes' => $classes,
            'motifs'  => $motifs,
            'errors'  => Session::getFlash('errors', []),
            'old'     => Session::getFlash('old', []),
        ]);
    }

    public function store(): void
    {
        $this->requirePermission('attendance.create');
        $this->verifyCsrf();

        $user = $this->currentUser();
        $dto  = AbsenceDTO::fromRequest($_POST);
        $errors = $dto->validate();

        $classeId      = (int)($_POST['classe_id'] ?? 0);
        $anneeScolaire = $_POST['annee_scolaire'] ?? date('Y') . '-' . (date('Y') + 1);

        if ($classeId <= 0) {
            $errors['classe_id'][] = 'La classe est obligatoire.';
        }

        if (!empty($errors)) {
            Session::flash('errors', $errors);
            Session::flash('old', $_POST);
            $this->redirect(BASE_URL . '/v2/vie-scolaire/absences/create');
        }

        try {
            $absenceId = $this->service->enregistrer($dto, $classeId, $anneeScolaire, (int)$user['id']);
            Session::flash('success', 'Absence enregistrée avec succès.');
            $this->redirect(BASE_URL . '/v2/vie-scolaire/absences/' . $absenceId);
        } catch (\InvalidArgumentException $e) {
            Session::flash('error', $e->getMessage());
            Session::flash('old', $_POST);
            $this->redirect(BASE_URL . '/v2/vie-scolaire/absences/create');
        }
    }

    // ── Édition ───────────────────────────────────────────────────────────────

    public function edit(string $id): void
    {
        $this->requirePermission('attendance.update');

        $absence = $this->service->findById((int)$id);
        if (!$absence) {
            Session::flash('error', 'Absence introuvable.');
            $this->redirect(BASE_URL . '/v2/vie-scolaire/absences');
        }

        $classes = (new ClasseModel())->findForSelect();

        $this->render('VieScolaire::absences/edit', [
            'title'   => 'Modifier une absence',
            'absence' => $absence,
            'classes' => $classes,
            'errors'  => Session::getFlash('errors', []),
        ]);
    }

    // ── Justification ─────────────────────────────────────────────────────────

    public function justifier(string $id): void
    {
        $this->requirePermission('attendance.justify');

        $absence = $this->service->findById((int)$id);
        if (!$absence) {
            Session::flash('error', 'Absence introuvable.');
            $this->redirect(BASE_URL . '/v2/vie-scolaire/absences');
        }
        $this->assertOwnEleve((int)$absence['eleve_id']);

        $motifs = $this->service->motifs();

        $this->render('VieScolaire::absences/justifier', [
            'title'   => 'Justifier une absence',
            'absence' => $absence,
            'motifs'  => $motifs,
            'errors'  => Session::getFlash('errors', []),
            'old'     => Session::getFlash('old', []),
        ]);
    }

    public function storeJustification(string $id): void
    {
        $this->requirePermission('attendance.justify');
        $this->verifyCsrf();

        $absenceId = (int)$id;
        $absence   = $this->service->findById($absenceId);
        if (!$absence) {
            Session::flash('error', 'Absence introuvable.');
            $this->redirect(BASE_URL . '/v2/vie-scolaire/absences');
        }
        $this->assertOwnEleve((int)$absence['eleve_id']);

        $user      = $this->currentUser();
        $dto       = JustificationDTO::fromRequest($_POST);
        $errors    = $dto->validate();

        if (!empty($errors)) {
            Session::flash('errors', $errors);
            Session::flash('old', $_POST);
            $this->redirect(BASE_URL . '/v2/vie-scolaire/absences/' . $absenceId . '/justifier');
        }

        try {
            $this->service->soumettrJustification($absenceId, $dto, (int)$user['id']);
            Session::flash('success', 'Justification soumise avec succès.');
            $this->redirect(BASE_URL . '/v2/vie-scolaire/absences/' . $absenceId);
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            Session::flash('error', $e->getMessage());
            $this->redirect(BASE_URL . '/v2/vie-scolaire/absences/' . $absenceId . '/justifier');
        }
    }

    public function valider(string $id): void
    {
        $this->requirePermission('attendance.validate');
        $this->verifyCsrf();

        $user = $this->currentUser();

        try {
            $this->service->validerJustification((int)$id, (int)$user['id']);
            Session::flash('success', 'Justification validée.');
        } catch (\RuntimeException $e) {
            Session::flash('error', $e->getMessage());
        }

        $this->redirect(BASE_URL . '/v2/vie-scolaire/absences/' . $id);
    }

    public function refuser(string $id): void
    {
        $this->requirePermission('attendance.validate');
        $this->verifyCsrf();

        $user       = $this->currentUser();
        $motifRefus = trim($_POST['motif_refus'] ?? '');

        try {
            $this->service->refuserJustification((int)$id, $motifRefus, (int)$user['id']);
            Session::flash('success', 'Justification refusée.');
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            Session::flash('error', $e->getMessage());
        }

        $this->redirect(BASE_URL . '/v2/vie-scolaire/absences/' . $id);
    }

    // ── Archivage ─────────────────────────────────────────────────────────────

    public function destroy(string $id): void
    {
        $this->requirePermission('attendance.delete');
        $this->verifyCsrf();

        $user = $this->currentUser();

        try {
            $this->service->archiver((int)$id, (int)$user['id']);
            Session::flash('success', 'Absence archivée.');
        } catch (\RuntimeException $e) {
            Session::flash('error', $e->getMessage());
        }

        $this->redirect(BASE_URL . '/v2/vie-scolaire/absences');
    }

    // ── Statistiques ──────────────────────────────────────────────────────────

    public function statistiques(): void
    {
        $this->requirePermission('attendance.view');

        $user          = $this->currentUser();
        $classeId      = (int)($_GET['classe_id'] ?? 0);
        $anneeScolaire = $_GET['annee_scolaire'] ?? date('Y') . '-' . (date('Y') + 1);
        $classes       = (new ClasseModel())->findForSelect();
        $statsClasse   = $classeId > 0
                             ? $this->service->statistiquesClasse($classeId, $anneeScolaire)
                             : [];

        $this->render('VieScolaire::absences/statistiques', [
            'title'         => 'Statistiques des absences',
            'classes'       => $classes,
            'classeId'      => $classeId,
            'anneeScolaire' => $anneeScolaire,
            'statsClasse'   => $statsClasse,
            'perms'         => $user['permissions'] ?? [],
        ]);
    }
}

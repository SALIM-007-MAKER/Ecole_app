<?php

namespace App\Modules\Academique\Controllers;

use App\Modules\Academique\DTO\EvaluationDTO;
use App\Modules\Academique\DTO\EvaluationFiltersDTO;
use App\Modules\Academique\Models\EvaluationModel;
use App\Modules\Academique\Policies\EvaluationPolicy;
use App\Modules\Academique\Repositories\EvaluationRepository;
use App\Modules\Academique\Services\EvaluationService;
use Core\Controller;
use Core\Session;

class EvaluationController extends Controller
{
    private EvaluationRepository $repo;
    private EvaluationService    $service;
    private EvaluationPolicy     $policy;

    public function __construct()
    {
        parent::__construct();
        $this->repo    = new EvaluationRepository();
        $this->service = new EvaluationService();
        $this->policy  = new EvaluationPolicy();
    }

    // ─── Liste ───────────────────────────────────────────────────────────────

    public function index(): void
    {
        $this->requirePermission('academique.evaluations.view');

        $filters    = EvaluationFiltersDTO::fromRequest($_GET);
        $pagination = $this->repo->paginate($filters->toArray(), $filters->page, $filters->perPage);
        $stats      = $this->repo->countStats();
        $user       = $this->currentUser();

        $this->render('Academique::evaluations/index', [
            'title'      => 'Évaluations V2',
            'pagination' => $pagination,
            'stats'      => $stats,
            'filters'    => $filters,
            'periodes'   => $this->repo->listPeriodesForSelect(),
            'classes'    => $this->repo->listClassesForSelect(),
            'matieres'   => $this->repo->listMatieresForSelect(),
            'types'      => $this->repo->listTypesForSelect(),
            'statuts'    => EvaluationModel::STATUT_LABELS,
            'colors'     => EvaluationModel::STATUT_COLORS,
            'perms'      => $user['permissions'] ?? [],
            'policy'     => $this->policy,
            'user'       => $user,
        ]);
    }

    // ─── Détail ──────────────────────────────────────────────────────────────

    public function show(string $id): void
    {
        $this->requirePermission('academique.evaluations.view');

        $evaluation = $this->repo->findWithDetails((int)$id);
        if (!$evaluation) {
            Session::flash('error', 'Évaluation introuvable.');
            $this->redirect(BASE_URL . '/v2/academique/evaluations');
        }

        $user = $this->currentUser();

        $this->render('Academique::evaluations/show', [
            'title'      => 'Évaluation — ' . $evaluation->libelle,
            'evaluation' => $evaluation,
            'statuts'    => EvaluationModel::STATUT_LABELS,
            'colors'     => EvaluationModel::STATUT_COLORS,
            'icons'      => EvaluationModel::STATUT_ICONS,
            'perms'      => $user['permissions'] ?? [],
            'policy'     => $this->policy,
            'user'       => $user,
        ]);
    }

    // ─── Création ────────────────────────────────────────────────────────────

    public function create(): void
    {
        $this->requirePermission('academique.evaluations.manage');

        $this->render('Academique::evaluations/form', [
            'title'       => 'Nouvelle évaluation',
            'evaluation'  => null,
            'periodes'    => $this->repo->listPeriodesForSelect(),
            'types'       => $this->repo->listTypesForSelect(),
            'matieres'    => $this->repo->listMatieresForSelect(),
            'classes'     => $this->repo->listClassesForSelect(),
            'enseignants' => $this->repo->listEnseignantsForSelect(),
            'statuts'     => EvaluationModel::STATUT_LABELS,
            'errors'      => Session::getFlash('errors', []),
            'old'         => Session::getFlash('old', []),
        ]);
    }

    public function store(): void
    {
        $this->requirePermission('academique.evaluations.manage');
        $this->verifyCsrf();

        $dto    = EvaluationDTO::fromRequest($_POST);
        $errors = $dto->validate();

        if (!empty($errors)) {
            Session::flash('errors', $errors);
            Session::flash('old', $_POST);
            $this->redirect(BASE_URL . '/v2/academique/evaluations/create');
        }

        try {
            $user         = $this->currentUser();
            $evaluationId = $this->service->creer($dto, (int)$user['id']);
            Session::flash('success', "Évaluation « {$dto->libelle} » créée en brouillon.");
            $this->redirect(BASE_URL . '/v2/academique/evaluations/' . $evaluationId);
        } catch (\RuntimeException $e) {
            Session::flash('errors', ['global' => [$e->getMessage()]]);
            Session::flash('old', $_POST);
            $this->redirect(BASE_URL . '/v2/academique/evaluations/create');
        }
    }

    // ─── Édition ─────────────────────────────────────────────────────────────

    public function edit(string $id): void
    {
        $evaluation = $this->repo->findWithDetails((int)$id);
        if (!$evaluation) {
            Session::flash('error', 'Évaluation introuvable.');
            $this->redirect(BASE_URL . '/v2/academique/evaluations');
        }

        $user = $this->currentUser();
        if (!$this->policy->canUpdate($user, $evaluation)) {
            $this->requirePermission('academique.evaluations.admin');
        }

        $this->render('Academique::evaluations/form', [
            'title'       => 'Modifier — ' . $evaluation->libelle,
            'evaluation'  => $evaluation,
            'periodes'    => $this->repo->listPeriodesForSelect(),
            'types'       => $this->repo->listTypesForSelect(),
            'matieres'    => $this->repo->listMatieresForSelect(),
            'classes'     => $this->repo->listClassesForSelect(),
            'enseignants' => $this->repo->listEnseignantsForSelect(),
            'statuts'     => EvaluationModel::STATUT_LABELS,
            'errors'      => Session::getFlash('errors', []),
            'old'         => Session::getFlash('old', []),
        ]);
    }

    public function update(string $id): void
    {
        $evaluationId = (int)$id;
        $evaluation   = $this->repo->findWithDetails($evaluationId);

        if (!$evaluation) {
            Session::flash('error', 'Évaluation introuvable.');
            $this->redirect(BASE_URL . '/v2/academique/evaluations');
        }

        $user = $this->currentUser();
        if (!$this->policy->canUpdate($user, $evaluation)) {
            $this->requirePermission('academique.evaluations.admin');
        }

        $this->verifyCsrf();

        $dto    = EvaluationDTO::fromRequest($_POST);
        $errors = $dto->validate();

        if (!empty($errors)) {
            Session::flash('errors', $errors);
            Session::flash('old', $_POST);
            $this->redirect(BASE_URL . '/v2/academique/evaluations/' . $evaluationId . '/edit');
        }

        $isAdmin = in_array('academique.evaluations.admin', $user['permissions'] ?? [], true);

        try {
            $this->service->modifier($evaluationId, $dto, (int)$user['id'], $isAdmin);
            Session::flash('success', "Évaluation mise à jour.");
            $this->redirect(BASE_URL . '/v2/academique/evaluations/' . $evaluationId);
        } catch (\RuntimeException $e) {
            Session::flash('errors', ['global' => [$e->getMessage()]]);
            Session::flash('old', $_POST);
            $this->redirect(BASE_URL . '/v2/academique/evaluations/' . $evaluationId . '/edit');
        }
    }

    // ─── Actions de cycle de vie ─────────────────────────────────────────────

    public function publier(string $id): void
    {
        $this->requirePermission('academique.evaluations.manage');
        $this->verifyCsrf();

        try {
            $user = $this->currentUser();
            $this->service->publier((int)$id, (int)$user['id']);
            Session::flash('success', "Évaluation publiée. La saisie de notes est maintenant ouverte.");
        } catch (\RuntimeException $e) {
            Session::flash('error', $e->getMessage());
        }

        $this->redirect(BASE_URL . '/v2/academique/evaluations/' . $id);
    }

    public function verrouiller(string $id): void
    {
        $this->requirePermission('academique.evaluations.manage');
        $this->verifyCsrf();

        try {
            $user = $this->currentUser();
            $this->service->verrouiller((int)$id, (int)$user['id']);
            Session::flash('success', "Évaluation verrouillée. La saisie de notes est fermée.");
        } catch (\RuntimeException $e) {
            Session::flash('error', $e->getMessage());
        }

        $this->redirect(BASE_URL . '/v2/academique/evaluations/' . $id);
    }

    public function deverrouiller(string $id): void
    {
        $this->requirePermission('academique.evaluations.admin');
        $this->verifyCsrf();

        try {
            $user = $this->currentUser();
            $this->service->deverrouiller((int)$id, (int)$user['id']);
            Session::flash('success', "Évaluation déverrouillée (action administrateur enregistrée).");
        } catch (\RuntimeException $e) {
            Session::flash('error', $e->getMessage());
        }

        $this->redirect(BASE_URL . '/v2/academique/evaluations/' . $id);
    }

    public function archiver(string $id): void
    {
        $this->requirePermission('academique.evaluations.admin');
        $this->verifyCsrf();

        try {
            $user = $this->currentUser();
            $this->service->archiver((int)$id, (int)$user['id']);
            Session::flash('success', "Évaluation archivée.");
        } catch (\RuntimeException $e) {
            Session::flash('error', $e->getMessage());
        }

        $this->redirect(BASE_URL . '/v2/academique/evaluations');
    }
}

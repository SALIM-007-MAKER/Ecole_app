<?php

namespace App\Modules\Academique\Controllers;

use App\Modules\Academique\DTO\TypeEvaluationDTO;
use App\Modules\Academique\DTO\TypeEvaluationFiltersDTO;
use App\Modules\Academique\Models\TypeEvaluationModel;
use App\Modules\Academique\Policies\TypeEvaluationPolicy;
use App\Modules\Academique\Repositories\TypeEvaluationRepository;
use App\Modules\Academique\Services\TypeEvaluationService;
use Core\Controller;
use Core\Session;

class TypeEvaluationController extends Controller
{
    private TypeEvaluationRepository $repo;
    private TypeEvaluationService    $service;
    private TypeEvaluationPolicy     $policy;

    public function __construct()
    {
        parent::__construct();
        $this->repo    = new TypeEvaluationRepository();
        $this->service = new TypeEvaluationService();
        $this->policy  = new TypeEvaluationPolicy();
    }

    // ─── Liste ───────────────────────────────────────────────────────────────

    public function index(): void
    {
        $this->requirePermission('academique.types_evaluations.view');

        $filters    = TypeEvaluationFiltersDTO::fromRequest($_GET);
        $pagination = $this->repo->paginate($filters->toArray(), $filters->page, $filters->perPage);
        $stats      = $this->repo->countStats();
        $user       = $this->currentUser();

        $this->render('Academique::types_evaluations/index', [
            'title'      => "Types d'évaluations",
            'pagination' => $pagination,
            'stats'      => $stats,
            'filters'    => $filters,
            'perms'      => $user['permissions'] ?? [],
            'policy'     => $this->policy,
            'user'       => $user,
        ]);
    }

    // ─── Détail ──────────────────────────────────────────────────────────────

    public function show(string $id): void
    {
        $this->requirePermission('academique.types_evaluations.view');

        $typeId = (int)$id;
        $type   = $this->repo->findWithStats($typeId);

        if (!$type) {
            Session::flash('error', "Type d'évaluation introuvable.");
            $this->redirect(BASE_URL . '/v2/academique/types-evaluations');
        }

        $user = $this->currentUser();

        $this->render('Academique::types_evaluations/show', [
            'title'  => "Type — {$type->nom}",
            'type'   => $type,
            'perms'  => $user['permissions'] ?? [],
            'policy' => $this->policy,
            'user'   => $user,
        ]);
    }

    // ─── Création ────────────────────────────────────────────────────────────

    public function create(): void
    {
        $this->requirePermission('academique.types_evaluations.manage');

        $this->render('Academique::types_evaluations/form', [
            'title'   => "Nouveau type d'évaluation",
            'type'    => null,
            'icones'  => TypeEvaluationModel::ICONES_SUGGERES,
            'errors'  => Session::getFlash('errors', []),
            'old'     => Session::getFlash('old', []),
        ]);
    }

    public function store(): void
    {
        $this->requirePermission('academique.types_evaluations.manage');
        $this->verifyCsrf();

        $dto    = TypeEvaluationDTO::fromRequest($_POST);
        $errors = $dto->validate(true);

        if (!empty($errors)) {
            Session::flash('errors', $errors);
            Session::flash('old', $_POST);
            $this->redirect(BASE_URL . '/v2/academique/types-evaluations/create');
        }

        try {
            $user   = $this->currentUser();
            $typeId = $this->service->creer($dto, (int)$user['id']);
            Session::flash('success', "Type « {$dto->nom} » créé avec succès.");
            $this->redirect(BASE_URL . '/v2/academique/types-evaluations/' . $typeId);
        } catch (\RuntimeException $e) {
            Session::flash('errors', ['global' => [$e->getMessage()]]);
            Session::flash('old', $_POST);
            $this->redirect(BASE_URL . '/v2/academique/types-evaluations/create');
        }
    }

    // ─── Édition ─────────────────────────────────────────────────────────────

    public function edit(string $id): void
    {
        $typeId = (int)$id;
        $type   = $this->repo->findWithStats($typeId);

        if (!$type) {
            Session::flash('error', "Type d'évaluation introuvable.");
            $this->redirect(BASE_URL . '/v2/academique/types-evaluations');
        }

        $user = $this->currentUser();
        if (!$this->policy->canUpdate($user, $type)) {
            $this->requirePermission('academique.types_evaluations.admin');
        }

        $this->render('Academique::types_evaluations/form', [
            'title'  => "Modifier — {$type->nom}",
            'type'   => $type,
            'icones' => TypeEvaluationModel::ICONES_SUGGERES,
            'errors' => Session::getFlash('errors', []),
            'old'    => Session::getFlash('old', []),
        ]);
    }

    public function update(string $id): void
    {
        $typeId = (int)$id;
        $type   = $this->repo->findWithStats($typeId);

        if (!$type) {
            Session::flash('error', "Type d'évaluation introuvable.");
            $this->redirect(BASE_URL . '/v2/academique/types-evaluations');
        }

        $user = $this->currentUser();
        if (!$this->policy->canUpdate($user, $type)) {
            $this->requirePermission('academique.types_evaluations.admin');
        }

        $this->verifyCsrf();

        $dto    = TypeEvaluationDTO::fromRequest($_POST);
        $errors = $dto->validate(false); // code not validated on update

        if (!empty($errors)) {
            Session::flash('errors', $errors);
            Session::flash('old', $_POST);
            $this->redirect(BASE_URL . '/v2/academique/types-evaluations/' . $typeId . '/edit');
        }

        $isAdmin = in_array('academique.types_evaluations.admin', $user['permissions'] ?? [], true);

        try {
            $this->service->modifier($typeId, $dto, (int)$user['id'], $isAdmin);
            Session::flash('success', "Type d'évaluation mis à jour.");
            $this->redirect(BASE_URL . '/v2/academique/types-evaluations/' . $typeId);
        } catch (\RuntimeException $e) {
            Session::flash('errors', ['global' => [$e->getMessage()]]);
            Session::flash('old', $_POST);
            $this->redirect(BASE_URL . '/v2/academique/types-evaluations/' . $typeId . '/edit');
        }
    }

    // ─── Actions de cycle de vie ─────────────────────────────────────────────

    public function activer(string $id): void
    {
        $this->requirePermission('academique.types_evaluations.manage');
        $this->verifyCsrf();

        try {
            $user = $this->currentUser();
            $this->service->activer((int)$id, (int)$user['id']);
            Session::flash('success', "Type activé avec succès.");
        } catch (\RuntimeException $e) {
            Session::flash('error', $e->getMessage());
        }

        $this->redirect(BASE_URL . '/v2/academique/types-evaluations/' . $id);
    }

    public function desactiver(string $id): void
    {
        $this->requirePermission('academique.types_evaluations.manage');
        $this->verifyCsrf();

        try {
            $user    = $this->currentUser();
            $isAdmin = in_array('academique.types_evaluations.admin', $user['permissions'] ?? [], true);
            $this->service->desactiver((int)$id, (int)$user['id'], $isAdmin);
            Session::flash('success', "Type désactivé. Il n'apparaîtra plus dans les sélecteurs.");
        } catch (\RuntimeException $e) {
            Session::flash('error', $e->getMessage());
        }

        $this->redirect(BASE_URL . '/v2/academique/types-evaluations/' . $id);
    }

    public function archiver(string $id): void
    {
        $this->requirePermission('academique.types_evaluations.admin');
        $this->verifyCsrf();

        try {
            $user = $this->currentUser();
            $this->service->archiver((int)$id, (int)$user['id']);
            Session::flash('success', "Type archivé définitivement.");
        } catch (\RuntimeException $e) {
            Session::flash('error', $e->getMessage());
        }

        $this->redirect(BASE_URL . '/v2/academique/types-evaluations');
    }
}

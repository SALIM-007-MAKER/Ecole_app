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

        $user = $this->currentUser();
        $peutConduite = in_array(EvaluationPolicy::PERMISSION_CONDUITE, $user['permissions'] ?? [], true);

        $this->render('Academique::evaluations/form', [
            'title'       => 'Nouvelle évaluation',
            'evaluation'  => null,
            'periodes'    => $this->repo->listPeriodesForSelect(),
            'types'       => $this->repo->listTypesForSelect(),
            'matieres'    => $this->repo->listMatieresForSelect($peutConduite),
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

        $user = $this->currentUser();
        if (!$this->policy->canCreate($user, $this->repo->matiereEstConduite($dto->matiereId) === true)) {
            Session::flash('errors', ['global' => ["La création d'une évaluation de Conduite est réservée aux profils autorisés à gérer la Conduite."]]);
            Session::flash('old', $_POST);
            $this->redirect(BASE_URL . '/v2/academique/evaluations/create');
        }

        try {
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
        $this->exigerModification($user, $evaluation);
        $peutConduite = in_array(EvaluationPolicy::PERMISSION_CONDUITE, $user['permissions'] ?? [], true);

        $this->render('Academique::evaluations/form', [
            'title'       => 'Modifier — ' . $evaluation->libelle,
            'evaluation'  => $evaluation,
            'periodes'    => $this->repo->listPeriodesForSelect(),
            'types'       => $this->repo->listTypesForSelect(),
            'matieres'    => $this->repo->listMatieresForSelect($peutConduite),
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
        $this->exigerModification($user, $evaluation);

        $this->verifyCsrf();

        $dto    = EvaluationDTO::fromRequest($_POST);
        $errors = $dto->validate();

        if (!empty($errors)) {
            Session::flash('errors', $errors);
            Session::flash('old', $_POST);
            $this->redirect(BASE_URL . '/v2/academique/evaluations/' . $evaluationId . '/edit');
        }

        $peutConduite = in_array(EvaluationPolicy::PERMISSION_CONDUITE, $user['permissions'] ?? [], true);
        $conduiteActuelle = $this->policy->estConduite($evaluation->matiere_est_conduite ?? null);
        $isAdmin = in_array('academique.evaluations.admin', $user['permissions'] ?? [], true) || ($conduiteActuelle && $peutConduite);

        if (!$peutConduite && $dto->matiereId !== (int)$evaluation->matiere_id
            && $this->repo->matiereEstConduite($dto->matiereId) === true) {
            Session::flash('errors', ['global' => ["Le passage en matière Conduite est réservé aux profils autorisés à gérer la Conduite."]]);
            Session::flash('old', $_POST);
            $this->redirect(BASE_URL . '/v2/academique/evaluations/' . $evaluationId . '/edit');
        }

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
        $this->exigerDroitConduite((int)$id);
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
        $this->exigerDroitConduite((int)$id);
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
        $this->requirePermission($this->droitAdminOuConduite((int)$id));
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
        $this->requirePermission($this->droitAdminOuConduite((int)$id));
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

    // ─── Conduite : droit dédié (marqueur matieres.est_conduite) ──────────────

    /** Matière ordinaire : comportement historique (droit .admin). Conduite : `academique.conduite.manage` exclusivement. */
    private function exigerModification(array $user, \stdClass $evaluation): void
    {
        if ($this->policy->canUpdate($user, $evaluation)) {
            return;
        }
        if ($this->policy->estConduite($evaluation->matiere_est_conduite ?? null)) {
            \Core\Logger::security('ACCESS_DENIED', 'Modification refusée sur une évaluation de Conduite (academique.conduite.manage requis)');
            http_response_code(403);
            $this->render('errors/403', ['title' => 'Permission insuffisante'], 'none');
            exit;
        }
        $this->requirePermission('academique.evaluations.admin');
    }

    /** Évaluation de Conduite (publier, verrouiller) : exige en plus `academique.conduite.manage`. */
    private function exigerDroitConduite(int $evaluationId): void
    {
        $evaluation = $this->repo->findWithDetails($evaluationId);
        if ($evaluation && $this->policy->estConduite($evaluation->matiere_est_conduite ?? null)) {
            $this->requirePermission(EvaluationPolicy::PERMISSION_CONDUITE);
        }
    }

    /** Droit exigé pour déverrouiller / archiver : `.admin` pour une matière ordinaire, `conduite.manage` pour la Conduite. */
    private function droitAdminOuConduite(int $evaluationId): string
    {
        $this->requireAuth();
        $evaluation = $this->repo->findWithDetails($evaluationId);
        return ($evaluation && $this->policy->estConduite($evaluation->matiere_est_conduite ?? null))
            ? EvaluationPolicy::PERMISSION_CONDUITE
            : 'academique.evaluations.admin';
    }
}

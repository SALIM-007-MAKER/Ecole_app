<?php

declare(strict_types=1);

namespace App\Modules\RH\Evaluations\Controllers;

use Core\Controller;
use Core\Session;
use App\Modules\RH\Evaluations\DTO\EvaluationDTO;
use App\Modules\RH\Evaluations\DTO\EvaluationFiltersDTO;
use App\Modules\RH\Evaluations\DTO\CampagneDTO;
use App\Modules\RH\Evaluations\Models\EvaluationModel;
use App\Modules\RH\Evaluations\Policies\EvaluationPolicy;
use App\Modules\RH\Evaluations\Repositories\EvaluationRepository;
use App\Modules\RH\Evaluations\Services\EvaluationService;

class EvaluationController extends Controller
{
    private EvaluationService    $service;
    private EvaluationRepository $repo;
    private EvaluationPolicy     $policy;

    public function __construct()
    {
        parent::__construct();
        $this->service = new EvaluationService();
        $this->repo    = new EvaluationRepository();
        $this->policy  = new EvaluationPolicy();
    }

    private function currentUser(): array { return Session::getUser() ?? []; }
    private function userId(): int        { return (int)($this->currentUser()['id'] ?? 0); }
    private function userName(): string
    {
        $u = $this->currentUser();
        return trim(($u['prenom'] ?? '') . ' ' . ($u['nom'] ?? '')) ?: 'Système';
    }

    // GET /v2/rh/evaluations
    public function index(): void
    {
        $this->requirePermission('evaluation.view');
        $filters = EvaluationFiltersDTO::fromRequest($_GET);
        $total   = $this->repo->count($filters);
        $items   = $this->repo->findAll($filters);
        $stats   = $this->repo->statistiques();
        $campagnes = $this->repo->findAllCampagnes();

        $this->render('RH::evaluations/index', [
            'items'     => $items,
            'filters'   => $filters,
            'stats'     => $stats,
            'campagnes' => $campagnes,
            'total'     => $total,
            'pages'     => (int)ceil($total / $filters->perPage),
            'model'     => EvaluationModel::class,
            'policy'    => $this->policy,
            'user'      => $this->currentUser(),
        ]);
    }

    // GET /v2/rh/evaluations/campagnes
    public function campagnes(): void
    {
        $this->requirePermission('evaluation.view');
        $campagnes = $this->repo->findAllCampagnes(
            (int)($_GET['annee'] ?? 0),
            $_GET['statut'] ?? ''
        );
        $stats = $this->repo->statistiques();

        $this->render('RH::evaluations/campagnes', [
            'campagnes' => $campagnes,
            'stats'     => $stats,
            'annee'     => (int)($_GET['annee'] ?? date('Y')),
            'model'     => EvaluationModel::class,
            'policy'    => $this->policy,
            'user'      => $this->currentUser(),
        ]);
    }

    // GET /v2/rh/evaluations/campagnes/create
    public function createCampagne(): void
    {
        $this->requirePermission('evaluation.create');
        $criteres = $this->repo->findAllCriteres();

        $this->render('RH::evaluations/create_campagne', [
            'criteres' => $criteres,
            'model'    => EvaluationModel::class,
            'old'      => [],
            'errors'   => [],
        ]);
    }

    // POST /v2/rh/evaluations/campagnes
    public function storeCampagne(): void
    {
        $this->requirePermission('evaluation.create');
        $this->verifyCsrf();

        $dto    = CampagneDTO::fromRequest($_POST);
        $errors = $dto->validate();

        if ($errors) {
            $criteres = $this->repo->findAllCriteres();
            $this->render('RH::evaluations/create_campagne', [
                'criteres' => $criteres,
                'model'    => EvaluationModel::class,
                'old'      => $_POST,
                'errors'   => $errors,
            ]);
            return;
        }

        try {
            $id = $this->service->creerCampagne($dto, $this->userId());
            Session::flash('success', 'Campagne créée avec succès.');
            $this->redirect('/v2/rh/evaluations/campagnes');
        } catch (\Exception $e) {
            $criteres = $this->repo->findAllCriteres();
            $this->render('RH::evaluations/create_campagne', [
                'criteres' => $criteres,
                'model'    => EvaluationModel::class,
                'old'      => $_POST,
                'errors'   => ['global' => $e->getMessage()],
            ]);
        }
    }

    // POST /v2/rh/evaluations/campagnes/{id}/activer
    public function activerCampagne(int $id): void
    {
        $this->requirePermission('evaluation.create');
        $this->verifyCsrf();

        try {
            $this->service->activerCampagne($id, $this->userId());
            Session::flash('success', 'Campagne activée.');
        } catch (\Exception $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect('/v2/rh/evaluations/campagnes');
    }

    // POST /v2/rh/evaluations/campagnes/{id}/cloturer
    public function cloturerCampagne(int $id): void
    {
        $this->requirePermission('evaluation.validate');
        $this->verifyCsrf();

        try {
            $this->service->cloturerCampagne($id, $this->userId());
            Session::flash('success', 'Campagne clôturée.');
        } catch (\Exception $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect('/v2/rh/evaluations/campagnes');
    }

    // GET /v2/rh/evaluations/create
    public function create(): void
    {
        $this->requirePermission('evaluation.create');
        $campagnes = $this->repo->findAllCampagnes(0, 'active');
        $employes  = $this->loadEmployes();

        $this->render('RH::evaluations/create', [
            'campagnes' => $campagnes,
            'employes'  => $employes,
            'model'     => EvaluationModel::class,
            'old'       => [],
            'errors'    => [],
        ]);
    }

    // POST /v2/rh/evaluations
    public function store(): void
    {
        $this->requirePermission('evaluation.create');
        $this->verifyCsrf();

        $dto    = EvaluationDTO::fromRequest($_POST);
        $errors = $dto->validate();

        if ($errors) {
            $campagnes = $this->repo->findAllCampagnes(0, 'active');
            $employes  = $this->loadEmployes();
            $this->render('RH::evaluations/create', [
                'campagnes' => $campagnes,
                'employes'  => $employes,
                'model'     => EvaluationModel::class,
                'old'       => $_POST,
                'errors'    => $errors,
            ]);
            return;
        }

        try {
            $id = $this->service->creer($dto, $this->userId(), $this->userName());
            Session::flash('success', 'Évaluation créée.');
            $this->redirect('/v2/rh/evaluations/' . $id);
        } catch (\Exception $e) {
            $campagnes = $this->repo->findAllCampagnes(0, 'active');
            $employes  = $this->loadEmployes();
            $this->render('RH::evaluations/create', [
                'campagnes' => $campagnes,
                'employes'  => $employes,
                'model'     => EvaluationModel::class,
                'old'       => $_POST,
                'errors'    => ['global' => $e->getMessage()],
            ]);
        }
    }

    // GET /v2/rh/evaluations/{id}
    public function show(int $id): void
    {
        $this->requirePermission('evaluation.view');
        $eval      = $this->requireEval($id);
        $criteres  = $this->repo->findCritereScores($id);
        $historique= $this->repo->findHistorique($id);
        $plans     = $this->repo->findPlans($id);

        $this->render('RH::evaluations/show', [
            'eval'       => $eval,
            'criteres'   => $criteres,
            'historique' => $historique,
            'plans'      => $plans,
            'model'      => EvaluationModel::class,
            'policy'     => $this->policy,
            'user'       => $this->currentUser(),
        ]);
    }

    // POST /v2/rh/evaluations/{id}/auto-eval
    public function soumettreAutoEval(int $id): void
    {
        $this->verifyCsrf();
        $eval = $this->requireEval($id);

        $user = $this->currentUser();
        if (!$this->policy->canSelfEvaluate($user, $eval) && !$this->policy->canUpdate($user)) {
            Session::flash('error', 'Accès refusé.');
            $this->redirect('/v2/rh/evaluations/' . $id);
            return;
        }

        try {
            $notes = (array)($_POST['notes'] ?? []);
            $commentaire = trim($_POST['commentaire_auto_eval'] ?? '');
            $this->service->soumettreAutoEval($id, $notes, $commentaire, $this->userId(), $this->userName());
            Session::flash('success', 'Auto-évaluation soumise.');
        } catch (\Exception $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect('/v2/rh/evaluations/' . $id);
    }

    // POST /v2/rh/evaluations/{id}/evaluer
    public function evaluerResponsable(int $id): void
    {
        $this->requirePermission('evaluation.update');
        $this->verifyCsrf();

        try {
            $notes = (array)($_POST['notes'] ?? []);
            $commentaire = trim($_POST['commentaire_evaluateur'] ?? '');
            $this->service->evaluerResponsable($id, $notes, $commentaire, $this->userId(), $this->userName());
            Session::flash('success', 'Notes responsable enregistrées.');
        } catch (\Exception $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect('/v2/rh/evaluations/' . $id);
    }

    // POST /v2/rh/evaluations/{id}/soumettre
    public function soumettre(int $id): void
    {
        $this->requirePermission('evaluation.update');
        $this->verifyCsrf();

        try {
            $this->service->soumettre($id, $this->userId(), $this->userName());
            Session::flash('success', 'Évaluation soumise pour validation.');
        } catch (\Exception $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect('/v2/rh/evaluations/' . $id);
    }

    // POST /v2/rh/evaluations/{id}/valider
    public function valider(int $id): void
    {
        $this->requirePermission('evaluation.validate');
        $this->verifyCsrf();

        try {
            $commentaire = trim($_POST['commentaire_validation'] ?? '');
            $this->service->valider($id, $commentaire, $this->userId(), $this->userName());
            Session::flash('success', 'Évaluation validée.');
        } catch (\Exception $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect('/v2/rh/evaluations/' . $id);
    }

    // POST /v2/rh/evaluations/{id}/publier
    public function publier(int $id): void
    {
        $this->requirePermission('evaluation.publish');
        $this->verifyCsrf();

        try {
            $this->service->publier($id, $this->userId(), $this->userName());
            Session::flash('success', 'Évaluation publiée à l\'employé.');
        } catch (\Exception $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect('/v2/rh/evaluations/' . $id);
    }

    // POST /v2/rh/evaluations/{id}/plan
    public function storePlan(int $id): void
    {
        $this->requirePermission('evaluation.update');
        $this->verifyCsrf();

        try {
            $planId = $this->service->creerPlanDeveloppement($id, $_POST, $this->userId());
            Session::flash('success', 'Plan de développement créé.');
        } catch (\Exception $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect('/v2/rh/evaluations/' . $id . '#plans');
    }

    // GET /v2/rh/evaluations/export
    public function export(): void
    {
        $this->requirePermission('evaluation.export');
        $filters = EvaluationFiltersDTO::fromRequest($_GET);
        $items   = $this->repo->findAll(new EvaluationFiltersDTO(
            q: $filters->q, statut: $filters->statut,
            campagneId: $filters->campagneId, employeId: $filters->employeId,
            annee: $filters->annee, page: 1, perPage: 9999
        ));

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="evaluations_' . date('Ymd') . '.csv"');
        echo "\xEF\xBB\xBF";
        $out = fopen('php://output', 'w');
        fputcsv($out, ['ID','Campagne','Employé','Matricule','Statut','Mention','Score final','Date validation'], ';');
        foreach ($items as $r) {
            fputcsv($out, [
                $r['id'], $r['campagne_libelle'], $r['employe_nom_complet'],
                $r['employe_matricule'], $r['statut'], $r['mention'] ?? '',
                $r['score_final'] ?? '', $r['date_validation'] ?? '',
            ], ';');
        }
        fclose($out);
        exit;
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function requireEval(int $id): array
    {
        $eval = $this->repo->findById($id);
        if (!$eval) {
            Session::flash('error', 'Évaluation introuvable.');
            $this->redirect('/v2/rh/evaluations');
        }
        return $eval;
    }

    private function loadEmployes(): array
    {
        $pdo = \Core\Database::getInstance()->getConnection();
        $stmt = $pdo->query(
            'SELECT id, CONCAT(prenom,\' \',nom) AS nom_complet, matricule
             FROM rh_employes WHERE actif = 1 AND deleted_at IS NULL ORDER BY nom, prenom'
        );
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}

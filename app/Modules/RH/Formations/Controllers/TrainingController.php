<?php

declare(strict_types=1);

namespace App\Modules\RH\Formations\Controllers;

use Core\Controller;
use Core\Session;
use Core\Database;
use PDO;
use App\Modules\RH\Formations\DTO\TrainingDTO;
use App\Modules\RH\Formations\DTO\SessionDTO;
use App\Modules\RH\Formations\DTO\TrainingFiltersDTO;
use App\Modules\RH\Formations\Models\TrainingModel;
use App\Modules\RH\Formations\Policies\TrainingPolicy;
use App\Modules\RH\Formations\Repositories\TrainingRepository;
use App\Modules\RH\Formations\Services\TrainingService;
use App\Modules\RH\Formations\Services\CertificationService;
use App\Modules\RH\Formations\Services\CompetencyService;
use App\Modules\RH\Formations\Services\LearningPathService;

class TrainingController extends Controller
{
    private TrainingService     $service;
    private CertificationService $certService;
    private CompetencyService   $compService;
    private LearningPathService $pathService;
    private TrainingRepository  $repo;
    private TrainingPolicy      $policy;

    public function __construct()
    {
        parent::__construct();
        $this->service     = new TrainingService();
        $this->certService = new CertificationService();
        $this->compService = new CompetencyService();
        $this->pathService = new LearningPathService();
        $this->repo        = new TrainingRepository();
        $this->policy      = new TrainingPolicy();
    }

    private function currentUser(): array { return Session::getUser() ?? []; }
    private function userId(): int        { return (int)($this->currentUser()['id'] ?? 0); }
    private function userName(): string
    {
        $u = $this->currentUser();
        return trim(($u['prenom'] ?? '') . ' ' . ($u['nom'] ?? '')) ?: 'Système';
    }

    // GET /v2/rh/formations
    public function index(): void
    {
        $this->requirePermission('training.view');
        $filters = TrainingFiltersDTO::fromRequest($_GET);
        $total   = $this->repo->countSessions($filters);
        $sessions = $this->repo->findSessions($filters);
        $stats   = $this->repo->statistiques();

        $this->render('RH::formations/index', [
            'sessions'  => $sessions,
            'filters'   => $filters,
            'stats'     => $stats,
            'total'     => $total,
            'pages'     => (int)ceil($total / $filters->perPage),
            'model'     => TrainingModel::class,
            'policy'    => $this->policy,
            'user'      => $this->currentUser(),
        ]);
    }

    // GET /v2/rh/formations/catalogue
    public function catalogue(): void
    {
        $this->requirePermission('training.view');
        $type       = trim($_GET['type'] ?? '');
        $formations = $this->repo->findAllFormations($type);
        $organismes = $this->repo->findAllOrganismes();

        $this->render('RH::formations/catalogue', [
            'formations' => $formations,
            'organismes' => $organismes,
            'type'       => $type,
            'model'      => TrainingModel::class,
            'policy'     => $this->policy,
            'user'       => $this->currentUser(),
        ]);
    }

    // GET /v2/rh/formations/catalogue/create
    public function createFormation(): void
    {
        $this->requirePermission('training.manage_catalog');
        $organismes = $this->repo->findAllOrganismes();
        $competences = $this->repo->findAllCompetences();

        $this->render('RH::formations/create_formation', [
            'organismes'  => $organismes,
            'competences' => $competences,
            'model'       => TrainingModel::class,
            'old'         => [],
            'errors'      => [],
        ]);
    }

    // POST /v2/rh/formations/catalogue
    public function storeFormation(): void
    {
        $this->requirePermission('training.manage_catalog');
        $this->verifyCsrf();

        $dto    = TrainingDTO::fromRequest($_POST);
        $errors = $dto->validate();

        if ($errors) {
            $this->render('RH::formations/create_formation', [
                'organismes'  => $this->repo->findAllOrganismes(),
                'competences' => $this->repo->findAllCompetences(),
                'model'       => TrainingModel::class,
                'old'         => $_POST,
                'errors'      => $errors,
            ]);
            return;
        }

        try {
            $id = $this->service->creerFormation($dto, $this->userId());
            Session::flash('success', 'Formation ajoutée au catalogue.');
            $this->redirect('/v2/rh/formations/catalogue');
        } catch (\Exception $e) {
            $this->render('RH::formations/create_formation', [
                'organismes'  => $this->repo->findAllOrganismes(),
                'competences' => $this->repo->findAllCompetences(),
                'model'       => TrainingModel::class,
                'old'         => $_POST,
                'errors'      => ['global' => $e->getMessage()],
            ]);
        }
    }

    // GET /v2/rh/formations/sessions/create
    public function createSession(): void
    {
        $this->requirePermission('training.create');
        $formations = $this->repo->findAllFormations();

        $this->render('RH::formations/create_session', [
            'formations'   => $formations,
            'preFormation' => (int)($_GET['formation_id'] ?? 0),
            'model'        => TrainingModel::class,
            'old'          => [],
            'errors'       => [],
        ]);
    }

    // POST /v2/rh/formations/sessions
    public function storeSession(): void
    {
        $this->requirePermission('training.create');
        $this->verifyCsrf();

        $dto    = SessionDTO::fromRequest($_POST);
        $errors = $dto->validate();

        if ($errors) {
            $this->render('RH::formations/create_session', [
                'formations'   => $this->repo->findAllFormations(),
                'preFormation' => $dto->formationId,
                'model'        => TrainingModel::class,
                'old'          => $_POST,
                'errors'       => $errors,
            ]);
            return;
        }

        try {
            $id = $this->service->creerSession($dto, $this->userId());
            Session::flash('success', 'Session créée.');
            $this->redirect('/v2/rh/formations/sessions/' . $id);
        } catch (\Exception $e) {
            $this->render('RH::formations/create_session', [
                'formations'   => $this->repo->findAllFormations(),
                'preFormation' => $dto->formationId,
                'model'        => TrainingModel::class,
                'old'          => $_POST,
                'errors'       => ['global' => $e->getMessage()],
            ]);
        }
    }

    // GET /v2/rh/formations/sessions/{id}
    public function showSession(int $id): void
    {
        $this->requirePermission('training.view');
        $session     = $this->requireSession($id);
        $inscriptions= $this->repo->findInscriptionsBySession($id);
        $competences = $this->repo->findFormationCompetences((int)$session['formation_id']);

        $this->render('RH::formations/show_session', [
            'session'      => $session,
            'inscriptions' => $inscriptions,
            'competences'  => $competences,
            'model'        => TrainingModel::class,
            'policy'       => $this->policy,
            'user'         => $this->currentUser(),
        ]);
    }

    // POST /v2/rh/formations/sessions/{id}/ouvrir
    public function ouvrirSession(int $id): void
    {
        $this->requirePermission('training.update');
        $this->verifyCsrf();
        try {
            $this->service->ouvrirSession($id, $this->userId());
            Session::flash('success', 'Session ouverte aux inscriptions.');
        } catch (\Exception $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect('/v2/rh/formations/sessions/' . $id);
    }

    // POST /v2/rh/formations/sessions/{id}/demarrer
    public function demarrerSession(int $id): void
    {
        $this->requirePermission('training.update');
        $this->verifyCsrf();
        try {
            $this->service->demarrerSession($id, $this->userId());
            Session::flash('success', 'Session démarrée.');
        } catch (\Exception $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect('/v2/rh/formations/sessions/' . $id);
    }

    // POST /v2/rh/formations/sessions/{id}/terminer
    public function terminerSession(int $id): void
    {
        $this->requirePermission('training.validate');
        $this->verifyCsrf();
        try {
            $this->service->terminerSession($id, $this->userId());
            Session::flash('success', 'Session terminée.');
        } catch (\Exception $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect('/v2/rh/formations/sessions/' . $id);
    }

    // POST /v2/rh/formations/sessions/{id}/annuler
    public function annulerSession(int $id): void
    {
        $this->requirePermission('training.update');
        $this->verifyCsrf();
        try {
            $this->service->annulerSession($id, $this->userId());
            Session::flash('success', 'Session annulée.');
        } catch (\Exception $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect('/v2/rh/formations/sessions/' . $id);
    }

    // POST /v2/rh/formations/sessions/{id}/inscrire
    public function inscrire(int $id): void
    {
        $this->requirePermission('training.enroll');
        $this->verifyCsrf();
        $employeId = (int)($_POST['employe_id'] ?? 0);
        if ($employeId <= 0) {
            Session::flash('error', 'Employé requis.');
            $this->redirect('/v2/rh/formations/sessions/' . $id);
            return;
        }
        try {
            $this->service->inscrire($id, $employeId, $this->userId());
            Session::flash('success', 'Employé inscrit à la session.');
        } catch (\Exception $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect('/v2/rh/formations/sessions/' . $id);
    }

    // POST /v2/rh/formations/inscriptions/{inscId}/presence
    public function marquerPresence(int $inscId): void
    {
        $this->requirePermission('training.update');
        $this->verifyCsrf();
        try {
            $present = isset($_POST['present']) && $_POST['present'] === '1';
            $this->service->marquerPresence($inscId, $present, $this->userId());
            Session::flash('success', 'Présence enregistrée.');
        } catch (\Exception $e) {
            Session::flash('error', $e->getMessage());
        }
        $sessionId = (int)($_POST['session_id'] ?? 0);
        $this->redirect('/v2/rh/formations/sessions/' . $sessionId);
    }

    // POST /v2/rh/formations/inscriptions/{inscId}/valider
    public function validerInscription(int $inscId): void
    {
        $this->requirePermission('training.validate');
        $this->verifyCsrf();
        try {
            $note = ($_POST['note_evaluation'] ?? '') !== '' ? (float)$_POST['note_evaluation'] : null;
            $com  = trim($_POST['commentaire_evaluation'] ?? '');
            $this->service->validerInscription($inscId, $note, $com ?: null, $this->userId());
            Session::flash('success', 'Participation validée, attestation accordée.');
        } catch (\Exception $e) {
            Session::flash('error', $e->getMessage());
        }
        $sessionId = (int)($_POST['session_id'] ?? 0);
        $this->redirect('/v2/rh/formations/sessions/' . $sessionId);
    }

    // POST /v2/rh/formations/inscriptions/{inscId}/annuler
    public function annulerInscription(int $inscId): void
    {
        $this->requirePermission('training.enroll');
        $this->verifyCsrf();
        try {
            $this->service->annulerInscription($inscId, $this->userId());
            Session::flash('success', 'Inscription annulée.');
        } catch (\Exception $e) {
            Session::flash('error', $e->getMessage());
        }
        $sessionId = (int)($_POST['session_id'] ?? 0);
        $this->redirect('/v2/rh/formations/sessions/' . $sessionId);
    }

    // GET /v2/rh/formations/certifications
    public function certifications(): void
    {
        $this->requirePermission('training.view');
        $employeId = (int)($_GET['employe_id'] ?? 0);
        $certs     = $employeId > 0
            ? $this->repo->findCertificationsByEmploye($employeId)
            : $this->repo->findExpiringCertifications(60);
        $catalogue = $this->repo->findAllCertificationsCatalogue();
        $employes  = $this->loadEmployes();

        $this->render('RH::formations/certifications', [
            'certs'      => $certs,
            'catalogue'  => $catalogue,
            'employes'   => $employes,
            'employeId'  => $employeId,
            'model'      => TrainingModel::class,
            'policy'     => $this->policy,
            'user'       => $this->currentUser(),
        ]);
    }

    // POST /v2/rh/formations/certifications
    public function storeCertification(): void
    {
        $this->requirePermission('training.validate');
        $this->verifyCsrf();
        $employeId = (int)($_POST['employe_id'] ?? 0);
        $certId    = (int)($_POST['certification_id'] ?? 0);
        if ($employeId <= 0 || $certId <= 0) {
            Session::flash('error', 'Employé et certification requis.');
            $this->redirect('/v2/rh/formations/certifications');
            return;
        }
        try {
            $this->certService->accorderCertification($employeId, $certId, $_POST, $this->userId());
            Session::flash('success', 'Certification accordée.');
        } catch (\Exception $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect('/v2/rh/formations/certifications');
    }

    // GET /v2/rh/formations/competences
    public function competences(): void
    {
        $this->requirePermission('training.view');
        $employeId  = (int)($_GET['employe_id'] ?? 0);
        $competences = $this->repo->findAllCompetences();
        $empComps    = $employeId > 0 ? $this->repo->findCompetencesByEmploye($employeId) : [];
        $employes    = $this->loadEmployes();

        $this->render('RH::formations/competences', [
            'competences' => $competences,
            'empComps'    => $empComps,
            'employes'    => $employes,
            'employeId'   => $employeId,
            'model'       => TrainingModel::class,
            'policy'      => $this->policy,
            'user'        => $this->currentUser(),
        ]);
    }

    // POST /v2/rh/formations/competences
    public function storeCompetence(): void
    {
        $this->requirePermission('training.validate');
        $this->verifyCsrf();
        $employeId  = (int)($_POST['employe_id']   ?? 0);
        $competenceId = (int)($_POST['competence_id'] ?? 0);
        $niveau     = trim($_POST['niveau'] ?? 'debutant');
        if ($employeId <= 0 || $competenceId <= 0) {
            Session::flash('error', 'Employé et compétence requis.');
            $this->redirect('/v2/rh/formations/competences');
            return;
        }
        try {
            $this->compService->validerCompetence(
                $employeId, $competenceId, $niveau,
                ($p = $_POST['session_id'] ?? '') !== '' ? (int)$p : null,
                $this->userId(), $this->userName(),
                trim($_POST['notes'] ?? '') ?: null
            );
            Session::flash('success', 'Compétence validée.');
        } catch (\Exception $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect('/v2/rh/formations/competences?employe_id=' . $employeId);
    }

    // GET /v2/rh/formations/export
    public function export(): void
    {
        $this->requirePermission('training.export');
        $filters = TrainingFiltersDTO::fromRequest($_GET);
        $sessions = $this->repo->findSessions(new TrainingFiltersDTO(
            q: $filters->q, statut: $filters->statut, type: $filters->type,
            formationId: $filters->formationId, annee: $filters->annee,
            page: 1, perPage: 9999
        ));

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="formations_' . date('Ymd') . '.csv"');
        echo "\xEF\xBB\xBF";
        $out = fopen('php://output', 'w');
        fputcsv($out, ['ID','Code session','Formation','Type','Date début','Date fin','Lieu','Statut','Inscrits','Max'], ';');
        foreach ($sessions as $s) {
            fputcsv($out, [
                $s['id'], $s['code_session'], $s['formation_titre'],
                $s['formation_type'], $s['date_debut'], $s['date_fin'],
                $s['lieu'] ?? '', $s['statut'], $s['nb_inscrits'], $s['max_participants'],
            ], ';');
        }
        fclose($out);
        exit;
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function requireSession(int $id): array
    {
        $s = $this->repo->findSessionById($id);
        if (!$s) {
            Session::flash('error', 'Session introuvable.');
            $this->redirect('/v2/rh/formations');
        }
        return $s;
    }

    private function loadEmployes(): array
    {
        $pdo = Database::getInstance()->getConnection();
        return $pdo->query(
            'SELECT id, CONCAT(prenom,\' \',nom) AS nom_complet, matricule
             FROM rh_employes WHERE actif = 1 AND deleted_at IS NULL ORDER BY nom, prenom'
        )->fetchAll(PDO::FETCH_ASSOC);
    }
}

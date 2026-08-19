<?php

namespace App\Modules\VieScolaire\Discipline\Controllers;

use App\Modules\VieScolaire\Discipline\DTO\AppealDTO;
use App\Modules\VieScolaire\Discipline\DTO\DisciplineDTO;
use App\Modules\VieScolaire\Discipline\DTO\DisciplineFiltersDTO;
use App\Modules\VieScolaire\Discipline\DTO\SanctionDTO;
use App\Modules\VieScolaire\Discipline\Policies\DisciplinePolicy;
use App\Modules\VieScolaire\Discipline\Repositories\DisciplineRepository;
use App\Modules\VieScolaire\Discipline\Services\DisciplineService;
use App\Services\UploadService;
use App\Shared\Auth\EleveScopeTrait;
use Core\Controller;
use Core\View;

class DisciplineController extends Controller
{
    use EleveScopeTrait;

    private DisciplineService    $service;
    private DisciplineRepository $repo;
    private DisciplinePolicy     $policy;
    private UploadService        $uploadService;

    public function __construct()
    {
        parent::__construct();
        $this->service       = new DisciplineService();
        $this->repo          = new DisciplineRepository();
        $this->policy        = new DisciplinePolicy();
        $this->uploadService = new UploadService();
    }

    private function handleDisciplineUpload(string $prefix): ?string
    {
        if (empty($_FILES['piece_jointe']['tmp_name'])) {
            return null;
        }
        $validation = $this->uploadService->validate($_FILES['piece_jointe'], 'piece_jointe_discipline');
        if (!$validation['ok']) {
            \Core\Session::flash('error', implode(' ', $validation['errors']));
            return null;
        }
        try {
            return $this->uploadService->upload($_FILES['piece_jointe'], 'piece_jointe_discipline', $prefix);
        } catch (\Throwable $e) {
            \Core\Session::flash('error', $e->getMessage());
            return null;
        }
    }

    // ── Liste dossiers ────────────────────────────────────────────────────────

    public function index(): void
    {
        $this->requireAuth();
        $user = $this->currentUser();
        $this->requirePermission('discipline.view');

        $filters = DisciplineFiltersDTO::fromRequest($_GET);

        $scope = $this->myEleveIds();
        if ($scope !== null) {
            $filters = $filters->withEleveIds($scope);
        }

        $result  = $this->service->paginateDossiers($filters);

        $this->view->render('VieScolaire::discipline/index', [
            'user'    => $user,
            'policy'  => $this->policy,
            'dossiers'=> $result['data'],
            'total'   => $result['total'],
            'page'    => $result['page'],
            'pages'   => $result['pages'],
            'filters' => $filters,
        ]);
    }

    // ── Statistiques ──────────────────────────────────────────────────────────

    public function statistiques(): void
    {
        $this->requireAuth();
        $user = $this->currentUser();
        $this->requirePermission('discipline.view');

        $classeId = (int)($_GET['classe_id'] ?? 0);
        $annee    = $_GET['annee_scolaire'] ?? date('Y') . '-' . (date('Y') + 1);
        $stats    = $classeId ? $this->service->statistiquesClasse($classeId, $annee) : [];

        $this->view->render('VieScolaire::discipline/statistiques', [
            'user'     => $user,
            'stats'    => $stats,
            'classeId' => $classeId,
            'annee'    => $annee,
        ]);
    }

    // ── Export CSV ────────────────────────────────────────────────────────────

    public function export(): void
    {
        $this->requireAuth();
        $this->requirePermission('discipline.export');

        $filters = DisciplineFiltersDTO::fromRequest(array_merge($_GET, ['per_page' => 500]));
        $dossiers = $this->repo->findDossiers($filters);

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="discipline_' . date('Y-m-d') . '.csv"');
        $f = fopen('php://output', 'w');
        fprintf($f, chr(0xEF) . chr(0xBB) . chr(0xBF));
        fputcsv($f, ['ID Dossier', 'Élève', 'Classe', 'Année', 'Statut', 'Nb Incidents', 'Créé le'], ';');

        foreach ($dossiers as $d) {
            fputcsv($f, [
                $d['id'],
                $d['eleve_nom'] . ' ' . $d['eleve_prenom'],
                $d['classe_nom'],
                $d['annee_scolaire'],
                $d['statut'],
                $d['nb_incidents'],
                $d['created_at'],
            ], ';');
        }
        fclose($f);
        exit;
    }

    // ── Créer incident ────────────────────────────────────────────────────────

    public function create(): void
    {
        $this->requireAuth();
        $user = $this->currentUser();
        $this->requirePermission('discipline.create');

        $categories = $this->service->categories();
        $classes    = (new \App\Models\ClasseModel())->findAll();
        $eleves     = (new \App\Models\EleveModel())->findAll('nom', 'ASC');

        $this->view->render('VieScolaire::discipline/create', [
            'user'      => $user,
            'categories'=> $categories,
            'classes'   => $classes,
            'eleves'    => $eleves,
        ]);
    }

    public function store(): void
    {
        $this->requireAuth();
        $this->verifyCsrf();
        $this->requirePermission('discipline.create');
        $user = $this->currentUser();

        $uploadedFile = $this->handleDisciplineUpload('incident_');

        try {
            $dto      = DisciplineDTO::fromRequest($_POST, $uploadedFile);
            $errors   = $dto->validate();
            if (!empty($errors)) {
                \Core\Session::flash('error', implode('<br>', $errors));
                $this->redirect('/v2/vie-scolaire/discipline/create');
                exit;
            }

            $incident = $this->service->signalerIncident($dto, (int)$user['id']);
            \Core\Session::flash('success', 'Incident signalé avec succès.');
            $this->redirect('/v2/vie-scolaire/discipline/' . $incident['dossier_id']);
            exit;
        } catch (\Throwable $e) {
            \Core\Session::flash('error', $e->getMessage());
            $this->redirect('/v2/vie-scolaire/discipline/create');
            exit;
        }
    }

    // ── Détail dossier ────────────────────────────────────────────────────────

    public function show(int $id): void
    {
        $this->requireAuth();
        $user = $this->currentUser();
        $this->requirePermission('discipline.view');

        $dossier = $this->service->findDossier($id);
        if ($dossier === null) {
            http_response_code(404);
            $this->view->render('errors/404', ['user' => $user], 'none');
            return;
        }
        $this->assertOwnEleve((int)$dossier['eleve_id']);

        $incidents = $this->repo->findIncidentsByDossier($id);
        $sanctions = $this->repo->findSanctionsByDossier($id);
        $appels    = $this->repo->findAppelsByDossier($id);

        $this->view->render('VieScolaire::discipline/show', [
            'user'     => $user,
            'dossier'  => $dossier,
            'incidents'=> $incidents,
            'sanctions'=> $sanctions,
            'appels'   => $appels,
            'policy'   => $this->policy,
        ]);
    }

    // ── Traiter / Classer incident ────────────────────────────────────────────

    public function traiterIncident(int $incidentId): void
    {
        $this->requireAuth();
        $this->verifyCsrf();
        $this->requirePermission('discipline.update');
        $user = $this->currentUser();

        try {
            $this->service->traiterIncident($incidentId, (int)$user['id']);
            \Core\Session::flash('success', 'Incident marqué comme traité.');
        } catch (\Throwable $e) {
            \Core\Session::flash('error', $e->getMessage());
        }

        $incident = $this->repo->findIncidentById($incidentId);
        $dossierId = $incident['dossier_id'] ?? 0;
        $this->redirect('/v2/vie-scolaire/discipline/' . $dossierId);
        exit;
    }

    // ── Sanctionner ───────────────────────────────────────────────────────────

    public function sanctionner(int $dossierId): void
    {
        $this->requireAuth();
        $user = $this->currentUser();
        $this->requirePermission('discipline.sanction');

        $dossier = $this->service->findDossier($dossierId);
        if ($dossier === null) {
            http_response_code(404);
            $this->view->render('errors/404', ['user' => $user], 'none');
            return;
        }

        if (!$this->policy->canProposeSanction($user, $dossier)) {
            http_response_code(403);
            \Core\Session::flash('error', 'Dossier clos — aucune sanction possible.');
            $this->redirect('/v2/vie-scolaire/discipline/' . $dossierId);
            exit;
        }

        $incidents = $this->repo->findIncidentsByDossier($dossierId);

        $this->view->render('VieScolaire::discipline/sanctionner', [
            'user'     => $user,
            'dossier'  => $dossier,
            'incidents'=> $incidents,
            'types'    => SanctionDTO::TYPES,
        ]);
    }

    public function storeSanction(int $dossierId): void
    {
        $this->requireAuth();
        $this->verifyCsrf();
        $this->requirePermission('discipline.sanction');
        $user = $this->currentUser();

        try {
            $dto    = SanctionDTO::fromRequest($_POST);
            $errors = $dto->validate();
            if (!empty($errors)) {
                \Core\Session::flash('error', implode('<br>', $errors));
                $this->redirect('/v2/vie-scolaire/discipline/' . $dossierId . '/sanctionner');
                exit;
            }

            $this->service->prononcerSanction($dossierId, $dto, (int)$user['id']);
            \Core\Session::flash('success', 'Sanction prononcée.');
            $this->redirect('/v2/vie-scolaire/discipline/' . $dossierId);
            exit;
        } catch (\Throwable $e) {
            \Core\Session::flash('error', $e->getMessage());
            $this->redirect('/v2/vie-scolaire/discipline/' . $dossierId . '/sanctionner');
            exit;
        }
    }

    // ── Valider / Lever sanction ──────────────────────────────────────────────

    public function validerSanction(int $sanctionId): void
    {
        $this->requireAuth();
        $this->verifyCsrf();
        $this->requirePermission('discipline.validate');
        $user = $this->currentUser();

        try {
            $this->service->validerSanction($sanctionId, (int)$user['id']);
            \Core\Session::flash('success', 'Sanction validée.');
        } catch (\Throwable $e) {
            \Core\Session::flash('error', $e->getMessage());
        }

        $sanction  = $this->repo->findSanctionById($sanctionId);
        $dossierId = $sanction['dossier_id'] ?? 0;
        $this->redirect('/v2/vie-scolaire/discipline/' . $dossierId);
        exit;
    }

    public function leverSanction(int $sanctionId): void
    {
        $this->requireAuth();
        $this->verifyCsrf();
        $this->requirePermission('discipline.validate');
        $user = $this->currentUser();

        $motif = trim($_POST['motif'] ?? '');

        try {
            $this->service->leverSanction($sanctionId, $motif, (int)$user['id']);
            \Core\Session::flash('success', 'Sanction levée.');
        } catch (\Throwable $e) {
            \Core\Session::flash('error', $e->getMessage());
        }

        $sanction  = $this->repo->findSanctionById($sanctionId);
        $dossierId = $sanction['dossier_id'] ?? 0;
        $this->redirect('/v2/vie-scolaire/discipline/' . $dossierId);
        exit;
    }

    // ── Appel ─────────────────────────────────────────────────────────────────

    public function appelView(int $sanctionId): void
    {
        $this->requireAuth();
        $user = $this->currentUser();
        $this->requirePermission('discipline.view');

        $sanction = $this->repo->findSanctionById($sanctionId);
        if ($sanction === null) {
            http_response_code(404);
            $this->view->render('errors/404', ['user' => $user], 'none');
            return;
        }
        $sanctionDossier = $this->service->findDossier((int)$sanction['dossier_id']);
        if ($sanctionDossier !== null) {
            $this->assertOwnEleve((int)$sanctionDossier['eleve_id']);
        }

        if (!$this->policy->canAppeal($user, $sanction)) {
            \Core\Session::flash('error', 'Appel impossible sur cette sanction.');
            $this->redirect('/v2/vie-scolaire/discipline/' . $sanction['dossier_id']);
            exit;
        }

        $this->view->render('VieScolaire::discipline/appel', [
            'user'    => $user,
            'sanction'=> $sanction,
        ]);
    }

    public function soumettreAppel(int $sanctionId): void
    {
        $this->requireAuth();
        $this->verifyCsrf();
        $this->requirePermission('discipline.view');
        $user = $this->currentUser();

        $sanction = $this->repo->findSanctionById($sanctionId);
        if ($sanction === null) {
            http_response_code(404);
            $this->view->render('errors/404', ['user' => $user], 'none');
            return;
        }
        $sanctionDossier = $this->service->findDossier((int)$sanction['dossier_id']);
        if ($sanctionDossier !== null) {
            $this->assertOwnEleve((int)$sanctionDossier['eleve_id']);
        }

        $uploadedFile = $this->handleDisciplineUpload('appel_');

        try {
            $dto    = AppealDTO::fromRequest($_POST, $uploadedFile);
            $errors = $dto->validate();
            if (!empty($errors)) {
                \Core\Session::flash('error', implode('<br>', $errors));
                $this->redirect('/v2/vie-scolaire/discipline/sanctions/' . $sanctionId . '/appel');
                exit;
            }

            $this->service->soumettreAppel($sanctionId, $dto, (int)$user['id']);
            \Core\Session::flash('success', 'Appel soumis avec succès.');

            $sanction  = $this->repo->findSanctionById($sanctionId);
            $dossierId = $sanction['dossier_id'] ?? 0;
            $this->redirect('/v2/vie-scolaire/discipline/' . $dossierId);
            exit;
        } catch (\Throwable $e) {
            \Core\Session::flash('error', $e->getMessage());
            $this->redirect('/v2/vie-scolaire/discipline/sanctions/' . $sanctionId . '/appel');
            exit;
        }
    }

    public function traiterAppel(int $appelId): void
    {
        $this->requireAuth();
        $this->verifyCsrf();
        $this->requirePermission('discipline.validate');
        $user = $this->currentUser();

        $statut   = $_POST['statut']   ?? '';
        $decision = trim($_POST['decision'] ?? '');

        try {
            $this->service->traiterAppel($appelId, $decision, $statut, (int)$user['id']);
            \Core\Session::flash('success', 'Appel traité.');
        } catch (\Throwable $e) {
            \Core\Session::flash('error', $e->getMessage());
        }

        $dossierId = (int)($_POST['dossier_id'] ?? 0);
        $this->redirect('/v2/vie-scolaire/discipline/' . $dossierId);
        exit;
    }

    // ── Clôturer dossier ──────────────────────────────────────────────────────

    public function cloturer(int $dossierId): void
    {
        $this->requireAuth();
        $this->verifyCsrf();
        $this->requirePermission('discipline.validate');
        $user = $this->currentUser();

        try {
            $this->service->clotureDossier($dossierId, (int)$user['id']);
            \Core\Session::flash('success', 'Dossier disciplinaire clos.');
        } catch (\Throwable $e) {
            \Core\Session::flash('error', $e->getMessage());
        }

        $this->redirect('/v2/vie-scolaire/discipline/' . $dossierId);
        exit;
    }
}

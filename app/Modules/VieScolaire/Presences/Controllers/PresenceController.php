<?php

namespace App\Modules\VieScolaire\Presences\Controllers;

use App\Models\ClasseModel;
use App\Models\MatiereModel;
use App\Modules\VieScolaire\Presences\DTO\AttendanceFiltersDTO;
use App\Modules\VieScolaire\Presences\DTO\AttendanceSessionDTO;
use App\Modules\VieScolaire\Presences\DTO\PresenceDTO;
use App\Modules\VieScolaire\Presences\Policies\AttendancePolicy;
use App\Modules\VieScolaire\Presences\Repositories\AttendanceRepository;
use App\Modules\VieScolaire\Presences\Services\AttendanceService;
use Core\Controller;
use Core\Session;

class PresenceController extends Controller
{
    private AttendanceRepository $repo;
    private AttendanceService    $service;
    private AttendancePolicy     $policy;

    public function __construct()
    {
        parent::__construct();
        $this->repo    = new AttendanceRepository();
        $this->service = new AttendanceService();
        $this->policy  = new AttendancePolicy();
    }

    // ── Liste des sessions ────────────────────────────────────────────────────

    public function index(): void
    {
        $this->requirePermission('attendance.session.view');

        $user    = $this->currentUser();
        $filters = AttendanceFiltersDTO::fromRequest($_GET);
        $result  = $this->service->paginateSessions($filters);
        $classes = (new ClasseModel())->findForSelect();

        $this->render('VieScolaire::presences/index', [
            'title'   => 'Sessions d\'appel',
            'result'  => $result,
            'filters' => $filters,
            'classes' => $classes,
            'perms'   => $user['permissions'] ?? [],
        ]);
    }

    // ── Nouvelle session ──────────────────────────────────────────────────────

    public function create(): void
    {
        $this->requirePermission('attendance.session.create');

        $classes = (new ClasseModel())->findForSelect();
        $matieres = (new MatiereModel())->findAllActifs();

        $this->render('VieScolaire::presences/create', [
            'title'    => 'Ouvrir un appel',
            'classes'  => $classes,
            'matieres' => $matieres,
            'errors'   => Session::getFlash('errors', []),
            'old'      => Session::getFlash('old', []),
        ]);
    }

    public function store(): void
    {
        $this->requirePermission('attendance.session.create');
        $this->verifyCsrf();

        $user          = $this->currentUser();
        $dto           = AttendanceSessionDTO::fromRequest($_POST);
        $errors        = $dto->validate();
        $anneeScolaire = $_POST['annee_scolaire'] ?? date('Y') . '-' . (date('Y') + 1);

        if (!empty($errors)) {
            Session::flash('errors', $errors);
            Session::flash('old', $_POST);
            $this->redirect(BASE_URL . '/v2/vie-scolaire/presences/create');
        }

        try {
            $sessionId = $this->service->ouvrirSession($dto, (int)$user['id'], $anneeScolaire);
            Session::flash('success', 'Session d\'appel ouverte. Pointez les élèves.');
            $this->redirect(BASE_URL . '/v2/vie-scolaire/presences/' . $sessionId);
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            Session::flash('error', $e->getMessage());
            Session::flash('old', $_POST);
            $this->redirect(BASE_URL . '/v2/vie-scolaire/presences/create');
        }
    }

    // ── Fiche + interface d'appel ─────────────────────────────────────────────

    public function show(string $id): void
    {
        $this->requirePermission('attendance.session.view');

        $sessionId = (int)$id;

        try {
            $data = $this->service->getSessionWithPresences($sessionId);
        } catch (\RuntimeException $e) {
            Session::flash('error', $e->getMessage());
            $this->redirect(BASE_URL . '/v2/vie-scolaire/presences');
        }

        $user = $this->currentUser();

        $this->render('VieScolaire::presences/show', [
            'title'          => 'Appel — ' . $data['session']['classe_nom'] . ' — ' . date('d/m/Y', strtotime($data['session']['date_appel'])),
            'session'        => $data['session'],
            'eleves'         => $data['eleves'],
            'presences'      => $data['presences'],
            'presencesIndex' => $data['presencesIndex'],
            'stats'          => $data['stats'],
            'perms'          => $user['permissions'] ?? [],
            'canUpdate'      => $this->policy->canModifySession($user, $data['session']),
            'canValidate'    => $this->policy->canValidate($user),
        ]);
    }

    // ── Enregistrement du pointage ────────────────────────────────────────────

    public function pointer(string $id): void
    {
        $this->requirePermission('attendance.session.update');
        $this->verifyCsrf();

        $sessionId = (int)$id;
        $user      = $this->currentUser();

        // Vérification que la session n'est pas validée avant de construire les DTOs
        $session = $this->service->findSession($sessionId);
        if (!$session) {
            Session::flash('error', 'Session introuvable.');
            $this->redirect(BASE_URL . '/v2/vie-scolaire/presences');
        }
        if (!$this->policy->canModifySession($user, $session)) {
            Session::flash('error', 'Cette session est validée et ne peut plus être modifiée.');
            $this->redirect(BASE_URL . '/v2/vie-scolaire/presences/' . $sessionId);
        }

        // Construction des DTOs depuis le formulaire bulk : presences[{eleve_id}][statut|...]
        $rawPresences = $_POST['presences'] ?? [];
        $dtos         = [];

        foreach ($rawPresences as $eleveId => $studentData) {
            $dto = PresenceDTO::fromBulkRequest((int)$eleveId, $studentData);
            if (!empty($dto->statut)) {
                $dtos[] = $dto;
            }
        }

        if (empty($dtos)) {
            Session::flash('error', 'Aucun pointage à enregistrer.');
            $this->redirect(BASE_URL . '/v2/vie-scolaire/presences/' . $sessionId);
        }

        try {
            $this->service->enregistrerPointages($sessionId, $dtos, (int)$user['id']);
            Session::flash('success', 'Pointage enregistré avec succès.');
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            Session::flash('error', $e->getMessage());
        }

        $this->redirect(BASE_URL . '/v2/vie-scolaire/presences/' . $sessionId);
    }

    // ── Validation de session ─────────────────────────────────────────────────

    public function valider(string $id): void
    {
        $this->requirePermission('attendance.session.validate');

        $sessionId = (int)$id;
        $session   = $this->service->findSession($sessionId);

        if (!$session) {
            Session::flash('error', 'Session introuvable.');
            $this->redirect(BASE_URL . '/v2/vie-scolaire/presences');
        }

        $stats = $this->repo->statsParSession($sessionId);

        $this->render('VieScolaire::presences/valider', [
            'title'   => 'Valider l\'appel',
            'session' => $session,
            'stats'   => $stats,
        ]);
    }

    public function confirmerValidation(string $id): void
    {
        $this->requirePermission('attendance.session.validate');
        $this->verifyCsrf();

        $user = $this->currentUser();

        try {
            $this->service->validerSession((int)$id, (int)$user['id']);
            Session::flash('success', 'Appel validé avec succès.');
        } catch (\RuntimeException $e) {
            Session::flash('error', $e->getMessage());
        }

        $this->redirect(BASE_URL . '/v2/vie-scolaire/presences/' . $id);
    }

    // ── Historique des corrections ────────────────────────────────────────────

    public function historique(string $id): void
    {
        $this->requirePermission('attendance.session.view');

        $sessionId = (int)$id;
        $session   = $this->service->findSession($sessionId);

        if (!$session) {
            Session::flash('error', 'Session introuvable.');
            $this->redirect(BASE_URL . '/v2/vie-scolaire/presences');
        }

        $historique = $this->service->getSessionHistory($sessionId);

        $this->render('VieScolaire::presences/historique', [
            'title'      => 'Historique — Appel du ' . date('d/m/Y', strtotime($session['date_appel'])),
            'session'    => $session,
            'historique' => $historique,
        ]);
    }

    // ── Statistiques ──────────────────────────────────────────────────────────

    public function statistiques(): void
    {
        $this->requirePermission('attendance.session.view');

        $user          = $this->currentUser();
        $classeId      = (int)($_GET['classe_id'] ?? 0);
        $anneeScolaire = $_GET['annee_scolaire'] ?? date('Y') . '-' . (date('Y') + 1);
        $classes       = (new ClasseModel())->findForSelect();
        $statsClasse   = $classeId > 0
                             ? $this->service->statsClasse($classeId, $anneeScolaire)
                             : [];

        $this->render('VieScolaire::presences/statistiques', [
            'title'         => 'Statistiques des présences',
            'classes'       => $classes,
            'classeId'      => $classeId,
            'anneeScolaire' => $anneeScolaire,
            'statsClasse'   => $statsClasse,
            'perms'         => $user['permissions'] ?? [],
        ]);
    }

    // ── Archivage ─────────────────────────────────────────────────────────────

    public function destroy(string $id): void
    {
        $this->requirePermission('attendance.session.validate'); // seul admin/directeur peut supprimer
        $this->verifyCsrf();

        $user = $this->currentUser();

        try {
            $this->service->archiverSession((int)$id, (int)$user['id']);
            Session::flash('success', 'Session archivée.');
        } catch (\RuntimeException $e) {
            Session::flash('error', $e->getMessage());
        }

        $this->redirect(BASE_URL . '/v2/vie-scolaire/presences');
    }
}

<?php

namespace App\Modules\VieScolaire\Activites\Controllers;

use App\Modules\VieScolaire\Activites\DTO\ActivityDTO;
use App\Modules\VieScolaire\Activites\DTO\ActivityFiltersDTO;
use App\Modules\VieScolaire\Activites\DTO\InscriptionActivityDTO;
use App\Modules\VieScolaire\Activites\Policies\ActivityPolicy;
use App\Modules\VieScolaire\Activites\Services\ActivityService;
use Core\Controller;
use Core\Database;

class ActivityController extends Controller
{
    private ActivityService $service;
    private ActivityPolicy  $policy;

    public function __construct()
    {
        parent::__construct();
        $this->service = new ActivityService();
        $this->policy  = new ActivityPolicy();
    }

    // ── Index ─────────────────────────────────────────────────────────────────

    public function index(): void
    {
        $user = $this->requireAuth();
        $this->requirePermission('activity.view');

        $filters = ActivityFiltersDTO::fromRequest($_GET);
        $data    = $this->service->paginateActivities($filters);
        $cats    = $this->service->categories();
        $db      = Database::getInstance()->getConnection();
        $classes = $db->query("SELECT id, nom FROM classes ORDER BY nom")->fetchAll(\PDO::FETCH_ASSOC);
        $annees  = $db->query("SELECT DISTINCT annee_scolaire FROM vs_activites ORDER BY annee_scolaire DESC")->fetchAll(\PDO::FETCH_ASSOC);

        $this->render('VieScolaire::activites/index', array_merge($data, [
            'user'       => $user,
            'categories' => $cats,
            'classes'    => $classes,
            'annees'     => $annees,
            'filters'    => $filters,
        ]));
    }

    // ── Détail ────────────────────────────────────────────────────────────────

    public function show(int $id): void
    {
        $user = $this->requireAuth();
        $this->requirePermission('activity.view');

        $activity = $this->service->findActivity($id);
        if ($activity === null) {
            http_response_code(404);
            $this->render('VieScolaire::activites/index', ['error' => 'Activité introuvable.', 'user' => $user]);
            return;
        }

        $inscriptions = $this->service->inscriptions($id);
        $historique   = $this->service->historique($id);

        $this->render('VieScolaire::activites/show', [
            'user'         => $user,
            'activity'     => $activity,
            'inscriptions' => $inscriptions,
            'historique'   => $historique,
            'policy'       => $this->policy,
        ]);
    }

    // ── Création ─────────────────────────────────────────────────────────────

    public function create(): void
    {
        $user = $this->requireAuth();
        $this->requirePermission('activity.create');

        [$classes, $enseignants, $categories] = $this->formData();

        $this->render('VieScolaire::activites/create', [
            'user'        => $user,
            'categories'  => $categories,
            'classes'     => $classes,
            'enseignants' => $enseignants,
        ]);
    }

    public function store(): void
    {
        $user = $this->requireAuth();
        $this->requirePermission('activity.create');
        $this->verifyCsrf();

        $dto    = ActivityDTO::fromRequest($_POST);
        $errors = $dto->validate();

        if (!empty($errors)) {
            \Core\Session::flash('error', implode('<br>', $errors));
            $this->redirect('/v2/vie-scolaire/activites/create');
            exit;
        }

        try {
            $activity = $this->service->creerActivite($dto, $user['id']);
            \Core\Session::flash('success', 'Activité créée avec succès.');
            $this->redirect("/v2/vie-scolaire/activites/{$activity['id']}");
        } catch (\RuntimeException $e) {
            \Core\Session::flash('error', $e->getMessage());
            $this->redirect('/v2/vie-scolaire/activites/create');
        }
        exit;
    }

    // ── Modification ──────────────────────────────────────────────────────────

    public function edit(int $id): void
    {
        $user = $this->requireAuth();
        $this->requirePermission('activity.update');

        $activity = $this->service->findActivity($id);
        if ($activity === null || !$this->policy->canModifyActivity($user, $activity)) {
            \Core\Session::flash('error', 'Action non autorisée.');
            $this->redirect('/v2/vie-scolaire/activites');
            exit;
        }

        [$classes, $enseignants, $categories] = $this->formData();

        $this->render('VieScolaire::activites/edit', [
            'user'        => $user,
            'activity'    => $activity,
            'categories'  => $categories,
            'classes'     => $classes,
            'enseignants' => $enseignants,
        ]);
    }

    public function update(int $id): void
    {
        $user = $this->requireAuth();
        $this->requirePermission('activity.update');
        $this->verifyCsrf();

        $activity = $this->service->findActivity($id);
        if ($activity === null || !$this->policy->canModifyActivity($user, $activity)) {
            \Core\Session::flash('error', 'Action non autorisée.');
            $this->redirect("/v2/vie-scolaire/activites/{$id}");
            exit;
        }

        $dto    = ActivityDTO::fromRequest($_POST);
        $errors = $dto->validate();

        if (!empty($errors)) {
            \Core\Session::flash('error', implode('<br>', $errors));
            $this->redirect("/v2/vie-scolaire/activites/{$id}/edit");
            exit;
        }

        try {
            $this->service->modifierActivite($id, $dto, $user['id']);
            \Core\Session::flash('success', 'Activité mise à jour.');
        } catch (\RuntimeException $e) {
            \Core\Session::flash('error', $e->getMessage());
        }
        $this->redirect("/v2/vie-scolaire/activites/{$id}");
        exit;
    }

    // ── Publication ───────────────────────────────────────────────────────────

    public function publier(int $id): void
    {
        $user = $this->requireAuth();
        $this->requirePermission('activity.publish');
        $this->verifyCsrf();

        $activity = $this->service->findActivity($id);
        if ($activity === null || !$this->policy->canPublishActivity($user, $activity)) {
            \Core\Session::flash('error', 'Action non autorisée.');
            $this->redirect("/v2/vie-scolaire/activites/{$id}");
            exit;
        }

        try {
            $this->service->publierActivite($id, $user['id']);
            \Core\Session::flash('success', 'Activité publiée.');
        } catch (\RuntimeException $e) {
            \Core\Session::flash('error', $e->getMessage());
        }
        $this->redirect("/v2/vie-scolaire/activites/{$id}");
        exit;
    }

    // ── Annulation ────────────────────────────────────────────────────────────

    public function annuler(int $id): void
    {
        $user = $this->requireAuth();
        $this->requirePermission('activity.validate');
        $this->verifyCsrf();

        $activity = $this->service->findActivity($id);
        if ($activity === null || !$this->policy->canCancelActivity($user, $activity)) {
            \Core\Session::flash('error', 'Action non autorisée.');
            $this->redirect("/v2/vie-scolaire/activites/{$id}");
            exit;
        }

        $motif = trim($_POST['motif_annulation'] ?? '');

        try {
            $this->service->annulerActivite($id, $motif, $user['id']);
            \Core\Session::flash('success', 'Activité annulée.');
        } catch (\RuntimeException $e) {
            \Core\Session::flash('error', $e->getMessage());
        }
        $this->redirect("/v2/vie-scolaire/activites/{$id}");
        exit;
    }

    // ── Inscriptions ─────────────────────────────────────────────────────────

    public function inscrireForm(int $id): void
    {
        $user = $this->requireAuth();
        $this->requirePermission('activity.update');

        $activity = $this->service->findActivity($id);
        if ($activity === null) {
            \Core\Session::flash('error', 'Activité introuvable.');
            $this->redirect('/v2/vie-scolaire/activites');
            exit;
        }

        $db    = Database::getInstance()->getConnection();
        $eleves = $db->query("SELECT e.id, e.nom, e.prenom, e.matricule FROM eleves e ORDER BY e.nom, e.prenom")->fetchAll(\PDO::FETCH_ASSOC);

        $this->render('VieScolaire::activites/inscrire', [
            'user'     => $user,
            'activity' => $activity,
            'eleves'   => $eleves,
        ]);
    }

    public function inscrireEleve(int $id): void
    {
        $user = $this->requireAuth();
        $this->requirePermission('activity.update');
        $this->verifyCsrf();

        $dto    = InscriptionActivityDTO::fromRequest($_POST + ['activite_id' => $id]);
        $errors = $dto->validate();

        if (!empty($errors)) {
            \Core\Session::flash('error', implode('<br>', $errors));
            $this->redirect("/v2/vie-scolaire/activites/{$id}/inscrire");
            exit;
        }

        try {
            $result = $this->service->inscrireEleve($dto, $user['id']);
            $msg = $result['statut'] === 'inscrit'
                ? 'Élève inscrit à l\'activité.'
                : 'Élève ajouté à la liste d\'attente (capacité atteinte).';
            \Core\Session::flash('success', $msg);
        } catch (\RuntimeException $e) {
            \Core\Session::flash('error', $e->getMessage());
        }
        $this->redirect("/v2/vie-scolaire/activites/{$id}");
        exit;
    }

    public function annulerInscription(int $inscriptionId): void
    {
        $user = $this->requireAuth();
        $this->requirePermission('activity.update');
        $this->verifyCsrf();

        try {
            $this->service->annulerInscription($inscriptionId, $user['id']);
            \Core\Session::flash('success', 'Inscription annulée.');
        } catch (\RuntimeException $e) {
            \Core\Session::flash('error', $e->getMessage());
        }

        $ref = $_SERVER['HTTP_REFERER'] ?? '/v2/vie-scolaire/activites';
        $this->redirect("{$ref}");
        exit;
    }

    // ── Présences ─────────────────────────────────────────────────────────────

    public function marquerPresences(int $id): void
    {
        $user = $this->requireAuth();
        $this->requirePermission('activity.update');
        $this->verifyCsrf();

        $presences = $_POST['presences'] ?? [];

        try {
            $this->service->marquerPresences($id, $presences, $user['id']);
            \Core\Session::flash('success', 'Présences enregistrées.');
        } catch (\RuntimeException $e) {
            \Core\Session::flash('error', $e->getMessage());
        }
        $this->redirect("/v2/vie-scolaire/activites/{$id}");
        exit;
    }

    // ── Export CSV ────────────────────────────────────────────────────────────

    public function export(): void
    {
        $user = $this->requireAuth();
        $this->requirePermission('activity.export');

        $filters = ActivityFiltersDTO::fromRequest($_GET + ['per_page' => 1000]);
        $rows    = $this->service->paginateActivities($filters)['data'];

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="activites_' . date('Ymd') . '.csv"');
        echo "\xEF\xBB\xBF";

        $out = fopen('php://output', 'w');
        fputcsv($out, ['Titre', 'Catégorie', 'Date', 'Heure début', 'Heure fin', 'Lieu', 'Capacité', 'Inscrits', 'Statut'], ';');
        foreach ($rows as $r) {
            fputcsv($out, [
                $r['titre'],
                $r['categorie_nom'],
                $r['date_activite'],
                $r['heure_debut'],
                $r['heure_fin'],
                $r['lieu'] ?? '',
                $r['capacite_max'],
                $r['nb_inscrits'],
                $r['statut'],
            ], ';');
        }
        fclose($out);
        exit;
    }

    // ── Statistiques ─────────────────────────────────────────────────────────

    public function statistiques(): void
    {
        $user = $this->requireAuth();
        $this->requirePermission('activity.view');

        $annee = $_GET['annee'] ?? date('Y') . '-' . (date('Y') + 1);
        $stats = $this->service->statistiques($annee);

        $this->render('VieScolaire::activites/statistiques', [
            'user'  => $user,
            'stats' => $stats,
            'annee' => $annee,
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function formData(): array
    {
        $db   = Database::getInstance()->getConnection();
        $classes = $db->query("SELECT id, nom FROM classes ORDER BY nom")->fetchAll(\PDO::FETCH_ASSOC);
        $enseignants = $db->query("SELECT u.id, u.nom, u.prenom FROM users u JOIN user_roles ur ON ur.user_id = u.id JOIN roles r ON r.id = ur.role_id WHERE r.nom = 'enseignant' ORDER BY u.nom")->fetchAll(\PDO::FETCH_ASSOC);
        $categories  = $this->service->categories();
        return [$classes, $enseignants, $categories];
    }
}

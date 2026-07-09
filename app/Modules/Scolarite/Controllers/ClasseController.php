<?php

namespace App\Modules\Scolarite\Controllers;

use App\Models\ClasseModel as V1ClasseModel;
use App\Models\MatiereModel;
use App\Models\ProfesseurModel;
use App\Modules\Scolarite\DTO\ClasseDTO;
use App\Modules\Scolarite\DTO\ClasseFiltersDTO;
use App\Modules\Scolarite\Policies\ClassePolicy;
use App\Modules\Scolarite\Repositories\ClasseRepository;
use App\Modules\Scolarite\Services\AffectationService;
use App\Modules\Scolarite\Services\ClasseService;
use Core\Controller;
use Core\Session;

class ClasseController extends Controller
{
    private ClasseRepository  $repo;
    private ClasseService     $service;
    private AffectationService $affectation;
    private ClassePolicy      $policy;

    public function __construct()
    {
        parent::__construct();
        $this->repo        = new ClasseRepository();
        $this->service     = new ClasseService();
        $this->affectation = new AffectationService();
        $this->policy      = new ClassePolicy();
    }

    // ─── Liste ───────────────────────────────────────────────────────────────

    public function index(): void
    {
        $user = Session::getUser();
        if (!$this->policy->authorize($user, 'view') && !$this->policy->authorize($user, 'view.own')) {
            $this->requirePermission('classes.view');
        }

        $filters      = ClasseFiltersDTO::fromRequest($_GET);
        $classes      = $this->repo->listWithStats($filters->anneeScolaire);
        $stats        = $this->repo->countStats();
        $niveaux      = V1ClasseModel::NIVEAUX;

        // Grouper par niveau pour l'affichage
        $grouped = [];
        foreach ($classes as $c) {
            $grouped[$c->niveau][] = $c;
        }

        $this->render('Scolarite::classes/index', compact(
            'grouped', 'stats', 'filters', 'niveaux',
        ));
    }

    // ─── Détail ──────────────────────────────────────────────────────────────

    public function show(string $id): void
    {
        $this->requireAuth();
        $classeId = (int)$id;
        $classe   = $this->repo->findWithDetails($classeId);

        if (!$classe) {
            Session::flash('error', "Classe introuvable.");
            $this->redirect('/v2/scolarite/classes');
            return;
        }

        $user = Session::getUser();
        if (!$this->policy->authorize($user, 'view') && !$this->policy->authorize($user, 'view.own', $classe)) {
            $this->requirePermission('classes.view');
        }

        $eleves           = $this->repo->getEleves($classeId);
        $elevesDisponibles = $this->repo->getElevesDisponibles($classeId);
        $enseignements    = $this->repo->getEnseignements($classeId);
        $profModel        = new ProfesseurModel();
        $matiereModel     = new MatiereModel();
        $profs            = $profModel->findForSelect();
        $matieres         = $matiereModel->findForSelect();
        $annee            = $classe->annee_scolaire ?? '';

        $this->render('Scolarite::classes/show', compact(
            'classe', 'eleves', 'elevesDisponibles',
            'enseignements', 'profs', 'matieres', 'annee',
        ));
    }

    // ─── Création ────────────────────────────────────────────────────────────

    public function create(): void
    {
        $this->requirePermission('classes.create');

        $niveaux = V1ClasseModel::NIVEAUX;
        $old     = [];
        $errors  = [];

        $this->render('Scolarite::classes/form', compact('niveaux', 'old', 'errors'));
    }

    public function store(): void
    {
        $this->requirePermission('classes.create');
        $this->verifyCsrf();

        $dto    = ClasseDTO::fromRequest($_POST);
        $errors = $dto->validate();

        if (!empty($errors)) {
            $niveaux = V1ClasseModel::NIVEAUX;
            $old     = $_POST;
            $this->render('Scolarite::classes/form', compact('niveaux', 'old', 'errors'));
            return;
        }

        try {
            $classeId = $this->service->creer($dto, Session::getUser()['id']);
            Session::flash('success', "Classe {$dto->niveau} {$dto->nom} créée avec succès.");
            $this->redirect("/v2/scolarite/classes/{$classeId}");
        } catch (\RuntimeException $e) {
            $niveaux = V1ClasseModel::NIVEAUX;
            $old     = $_POST;
            $errors['global'][] = $e->getMessage();
            $this->render('Scolarite::classes/form', compact('niveaux', 'old', 'errors'));
        }
    }

    // ─── Édition ─────────────────────────────────────────────────────────────

    public function edit(string $id): void
    {
        $this->requirePermission('classes.update');

        $classeId = (int)$id;
        $classe   = $this->repo->findWithDetails($classeId);

        if (!$classe) {
            Session::flash('error', "Classe introuvable.");
            $this->redirect('/v2/scolarite/classes');
            return;
        }

        $niveaux = V1ClasseModel::NIVEAUX;
        $old     = [];
        $errors  = [];

        $this->render('Scolarite::classes/form', compact('classe', 'niveaux', 'old', 'errors'));
    }

    public function update(string $id): void
    {
        $this->requirePermission('classes.update');
        $this->verifyCsrf();

        $classeId = (int)$id;
        $classe   = $this->repo->findWithDetails($classeId);

        if (!$classe) {
            Session::flash('error', "Classe introuvable.");
            $this->redirect('/v2/scolarite/classes');
            return;
        }

        $dto    = ClasseDTO::fromRequest($_POST);
        $errors = $dto->validate();

        if (!empty($errors)) {
            $niveaux = V1ClasseModel::NIVEAUX;
            $old     = $_POST;
            $this->render('Scolarite::classes/form', compact('classe', 'niveaux', 'old', 'errors'));
            return;
        }

        try {
            $this->service->modifier($classeId, $dto, Session::getUser()['id']);
            Session::flash('success', "Classe modifiée avec succès.");
            $this->redirect("/v2/scolarite/classes/{$classeId}");
        } catch (\RuntimeException $e) {
            $niveaux = V1ClasseModel::NIVEAUX;
            $old     = $_POST;
            $errors['global'][] = $e->getMessage();
            $this->render('Scolarite::classes/form', compact('classe', 'niveaux', 'old', 'errors'));
        }
    }

    // ─── Suppression ─────────────────────────────────────────────────────────

    public function delete(string $id): void
    {
        $this->requirePermission('classes.delete');
        $this->verifyCsrf();

        try {
            $this->service->supprimer((int)$id, Session::getUser()['id']);
            Session::flash('success', "Classe supprimée.");
        } catch (\RuntimeException $e) {
            Session::flash('error', $e->getMessage());
        }

        $this->redirect('/v2/scolarite/classes');
    }

    // ─── Affectation élèves ──────────────────────────────────────────────────

    public function affecterEleve(string $id): void
    {
        $this->requirePermission('classes.update');
        $this->verifyCsrf();

        $classeId = (int)$id;
        $eleveId  = (int)($_POST['eleve_id'] ?? 0);

        if (!$eleveId) {
            Session::flash('error', "Élève non sélectionné.");
            $this->redirect("/v2/scolarite/classes/{$classeId}");
            return;
        }

        try {
            $this->affectation->affecterEleve($eleveId, $classeId, Session::getUser()['id']);
            Session::flash('success', "Élève affecté à la classe.");
        } catch (\RuntimeException $e) {
            Session::flash('error', $e->getMessage());
        }

        $this->redirect("/v2/scolarite/classes/{$classeId}");
    }

    public function retirerEleve(string $id): void
    {
        $this->requirePermission('classes.update');
        $this->verifyCsrf();

        $classeId = (int)$id;
        $eleveId  = (int)($_POST['eleve_id'] ?? 0);

        if (!$eleveId) {
            Session::flash('error', "Élève non spécifié.");
            $this->redirect("/v2/scolarite/classes/{$classeId}");
            return;
        }

        try {
            $this->affectation->retirerEleve($eleveId, Session::getUser()['id']);
            Session::flash('success', "Élève retiré de la classe.");
        } catch (\RuntimeException $e) {
            Session::flash('error', $e->getMessage());
        }

        $this->redirect("/v2/scolarite/classes/{$classeId}");
    }

    // ─── Affectation enseignants ─────────────────────────────────────────────

    public function affecterEnseignant(string $id): void
    {
        $this->requirePermission('classes.update');
        $this->verifyCsrf();

        $classeId    = (int)$id;
        $professeurId = (int)($_POST['professeur_id'] ?? 0);
        $matiereId   = (int)($_POST['matiere_id']    ?? 0);
        $annee       = trim($_POST['annee_scolaire'] ?? '');

        if (!$professeurId || !$matiereId || $annee === '') {
            Session::flash('error', "Enseignant, matière et année scolaire sont obligatoires.");
            $this->redirect("/v2/scolarite/classes/{$classeId}");
            return;
        }

        try {
            $this->affectation->affecterEnseignant(
                $classeId, $professeurId, $matiereId, $annee, Session::getUser()['id']
            );
            Session::flash('success', "Enseignant affecté à la classe.");
        } catch (\RuntimeException $e) {
            Session::flash('error', $e->getMessage());
        }

        $this->redirect("/v2/scolarite/classes/{$classeId}");
    }

    public function retirerEnseignant(string $id): void
    {
        $this->requirePermission('classes.update');
        $this->verifyCsrf();

        $classeId      = (int)$id;
        $enseignementId = (int)($_POST['enseignement_id'] ?? 0);

        if (!$enseignementId) {
            Session::flash('error', "Enseignement non spécifié.");
            $this->redirect("/v2/scolarite/classes/{$classeId}");
            return;
        }

        try {
            $this->affectation->retirerEnseignant($enseignementId, Session::getUser()['id']);
            Session::flash('success', "Enseignant retiré de la classe.");
        } catch (\RuntimeException $e) {
            Session::flash('error', $e->getMessage());
        }

        $this->redirect("/v2/scolarite/classes/{$classeId}");
    }
}

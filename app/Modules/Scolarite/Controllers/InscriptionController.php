<?php

namespace App\Modules\Scolarite\Controllers;

use App\Models\EleveModel;
use App\Modules\Scolarite\DTO\InscriptionDTO;
use App\Modules\Scolarite\DTO\InscriptionFiltersDTO;
use App\Modules\Scolarite\Models\InscriptionModel;
use App\Modules\Scolarite\Policies\InscriptionPolicy;
use App\Modules\Scolarite\Repositories\ClasseRepository;
use App\Modules\Scolarite\Repositories\EleveRepository;
use App\Modules\Scolarite\Repositories\InscriptionRepository;
use App\Modules\Scolarite\Services\InscriptionService;
use Core\Controller;
use Core\Session;

class InscriptionController extends Controller
{
    private InscriptionRepository $repo;
    private InscriptionService    $service;
    private InscriptionPolicy     $policy;

    public function __construct()
    {
        parent::__construct();
        $this->repo    = new InscriptionRepository();
        $this->service = new InscriptionService();
        $this->policy  = new InscriptionPolicy();
    }

    // ─── Liste ───────────────────────────────────────────────────────────────

    public function index(): void
    {
        $this->requirePermission('inscriptions.view');

        $filters   = InscriptionFiltersDTO::fromRequest($_GET);
        $result    = $this->repo->paginate(
            $filters->anneeScolaire,
            $filters->statut,
            $filters->classeId,
            $filters->q,
            $filters->page,
            $filters->perPage,
        );
        $stats     = $this->repo->countStats();
        $annees    = $this->repo->listAnneesScolaires();
        $classeRepo = new ClasseRepository();
        $classes   = $classeRepo->findForSelect();

        $this->render('Scolarite::inscriptions/index', compact(
            'result', 'stats', 'filters', 'annees', 'classes',
        ));
    }

    // ─── Détail ──────────────────────────────────────────────────────────────

    public function show(string $id): void
    {
        $this->requirePermission('inscriptions.view');

        $inscription = $this->repo->findWithDetails((int)$id);
        if (!$inscription) {
            Session::flash('error', "Inscription introuvable.");
            $this->redirect('/v2/scolarite/inscriptions');
            return;
        }

        $historique = $this->repo->historyByEleve((int)$inscription->eleve_id);

        $classeRepo = new ClasseRepository();
        $classes    = $classeRepo->findForSelect($inscription->annee_scolaire ?? '');

        $this->render('Scolarite::inscriptions/show', compact(
            'inscription', 'historique', 'classes',
        ));
    }

    // ─── Création ────────────────────────────────────────────────────────────

    public function create(): void
    {
        $this->requirePermission('inscriptions.create');

        $eleveRepo  = new EleveRepository();
        $classeRepo = new ClasseRepository();

        $eleves  = $eleveRepo->search('', 200);
        $classes = $classeRepo->findForSelect();
        $annees  = $this->repo->listAnneesScolaires();
        $old     = [];
        $errors  = [];

        $this->render('Scolarite::inscriptions/form', compact('eleves', 'classes', 'annees', 'old', 'errors'));
    }

    public function store(): void
    {
        $this->requirePermission('inscriptions.create');
        $this->verifyCsrf();

        $dto    = InscriptionDTO::fromRequest($_POST);
        $errors = $dto->validate();

        if (!empty($errors)) {
            $eleveRepo  = new EleveRepository();
            $classeRepo = new ClasseRepository();
            $eleves     = $eleveRepo->search('', 200);
            $classes    = $classeRepo->findForSelect();
            $annees     = $this->repo->listAnneesScolaires();
            $old        = $_POST;
            $this->render('Scolarite::inscriptions/form', compact('eleves', 'classes', 'annees', 'old', 'errors'));
            return;
        }

        try {
            $inscriptionId = $this->service->inscrire($dto, Session::getUser()['id']);
            Session::flash('success', "Inscription créée avec succès (en attente de validation).");
            $this->redirect("/v2/scolarite/inscriptions/{$inscriptionId}");
        } catch (\RuntimeException $e) {
            $eleveRepo  = new EleveRepository();
            $classeRepo = new ClasseRepository();
            $eleves     = $eleveRepo->search('', 200);
            $classes    = $classeRepo->findForSelect();
            $annees     = $this->repo->listAnneesScolaires();
            $old        = $_POST;
            $errors['global'][] = $e->getMessage();
            $this->render('Scolarite::inscriptions/form', compact('eleves', 'classes', 'annees', 'old', 'errors'));
        }
    }

    // ─── Édition ─────────────────────────────────────────────────────────────

    public function edit(string $id): void
    {
        $this->requirePermission('inscriptions.update');

        $inscription = $this->repo->findWithDetails((int)$id);
        if (!$inscription) {
            Session::flash('error', "Inscription introuvable.");
            $this->redirect('/v2/scolarite/inscriptions');
            return;
        }

        if (!in_array($inscription->statut, ['en_attente'], true)) {
            Session::flash('error', "Seules les inscriptions en attente peuvent être modifiées.");
            $this->redirect("/v2/scolarite/inscriptions/{$id}");
            return;
        }

        $eleveRepo  = new EleveRepository();
        $classeRepo = new ClasseRepository();
        $eleves     = $eleveRepo->search('', 200);
        $classes    = $classeRepo->findForSelect();
        $annees     = $this->repo->listAnneesScolaires();
        $old        = [];
        $errors     = [];

        $this->render('Scolarite::inscriptions/form', compact(
            'inscription', 'eleves', 'classes', 'annees', 'old', 'errors',
        ));
    }

    public function update(string $id): void
    {
        $this->requirePermission('inscriptions.update');
        $this->verifyCsrf();

        $inscriptionId = (int)$id;
        $inscription   = $this->repo->findWithDetails($inscriptionId);

        if (!$inscription) {
            Session::flash('error', "Inscription introuvable.");
            $this->redirect('/v2/scolarite/inscriptions');
            return;
        }

        $dto    = InscriptionDTO::fromRequest($_POST);
        $errors = $dto->validate();

        if (!empty($errors)) {
            $eleveRepo  = new EleveRepository();
            $classeRepo = new ClasseRepository();
            $eleves     = $eleveRepo->search('', 200);
            $classes    = $classeRepo->findForSelect();
            $annees     = $this->repo->listAnneesScolaires();
            $old        = $_POST;
            $this->render('Scolarite::inscriptions/form', compact(
                'inscription', 'eleves', 'classes', 'annees', 'old', 'errors',
            ));
            return;
        }

        try {
            $model = new InscriptionModel();
            $avant = (array)$model->findById($inscriptionId);
            $model->update($inscriptionId, [
                'classe_id'      => $dto->classeId,
                'annee_scolaire' => $dto->anneeScolaire,
                'notes'          => $dto->notes,
            ]);

            $changedFields = [];
            foreach (['classe_id', 'annee_scolaire', 'notes'] as $k) {
                $newKey = match ($k) {
                    'classe_id'      => $dto->classeId,
                    'annee_scolaire' => $dto->anneeScolaire,
                    'notes'          => $dto->notes,
                };
                if ((string)($avant[$k] ?? '') !== (string)$newKey) {
                    $changedFields['avant'][$k] = $avant[$k] ?? null;
                    $changedFields['apres'][$k] = $newKey;
                }
            }

            if (!empty($changedFields)) {
                \Core\EventDispatcher::dispatch(
                    new \App\Modules\Scolarite\Events\InscriptionUpdated(
                        $inscriptionId, Session::getUser()['id'], $changedFields
                    )
                );
            }

            Session::flash('success', "Inscription mise à jour.");
            $this->redirect("/v2/scolarite/inscriptions/{$inscriptionId}");
        } catch (\RuntimeException $e) {
            Session::flash('error', $e->getMessage());
            $this->redirect("/v2/scolarite/inscriptions/{$id}/edit");
        }
    }

    // ─── Validation / Rejet ──────────────────────────────────────────────────

    public function valider(string $id): void
    {
        $this->requirePermission('inscriptions.update');
        $this->verifyCsrf();

        try {
            $this->service->valider((int)$id, Session::getUser()['id']);
            Session::flash('success', "Inscription validée. L'élève est affecté à sa classe.");
        } catch (\RuntimeException $e) {
            Session::flash('error', $e->getMessage());
        }

        $this->redirect("/v2/scolarite/inscriptions/{$id}");
    }

    public function rejeter(string $id): void
    {
        $this->requirePermission('inscriptions.update');
        $this->verifyCsrf();

        $motif = trim($_POST['motif_rejet'] ?? '');

        try {
            $this->service->rejeter((int)$id, Session::getUser()['id'], $motif);
            Session::flash('success', "Inscription rejetée.");
        } catch (\RuntimeException $e) {
            Session::flash('error', $e->getMessage());
        }

        $this->redirect("/v2/scolarite/inscriptions/{$id}");
    }

    // ─── Annulation ──────────────────────────────────────────────────────────

    public function annuler(string $id): void
    {
        $this->requirePermission('inscriptions.update');
        $this->verifyCsrf();

        $motif = trim($_POST['motif'] ?? '');

        try {
            $this->service->annuler((int)$id, Session::getUser()['id'], $motif);
            Session::flash('success', "Inscription annulée.");
        } catch (\RuntimeException $e) {
            Session::flash('error', $e->getMessage());
        }

        $this->redirect("/v2/scolarite/inscriptions/{$id}");
    }

    // ─── Réinscription ───────────────────────────────────────────────────────

    public function reinscrire(string $id): void
    {
        $this->requirePermission('inscriptions.create');
        $this->verifyCsrf();

        $inscription   = $this->repo->findWithDetails((int)$id);
        if (!$inscription) {
            Session::flash('error', "Inscription introuvable.");
            $this->redirect('/v2/scolarite/inscriptions');
            return;
        }

        $nouvelleAnnee = trim($_POST['nouvelle_annee'] ?? '');
        $classeId      = !empty($_POST['classe_id']) ? (int)$_POST['classe_id'] : null;

        if (!preg_match('/^\d{4}-\d{4}$/', $nouvelleAnnee)) {
            Session::flash('error', "Format d'année scolaire invalide (ex: 2026-2027).");
            $this->redirect("/v2/scolarite/inscriptions/{$id}");
            return;
        }

        try {
            $newId = $this->service->reinscrire(
                (int)$inscription->eleve_id,
                $nouvelleAnnee,
                $classeId,
                Session::getUser()['id'],
            );
            Session::flash('success', "Réinscription créée pour l'année {$nouvelleAnnee}.");
            $this->redirect("/v2/scolarite/inscriptions/{$newId}");
        } catch (\RuntimeException $e) {
            Session::flash('error', $e->getMessage());
            $this->redirect("/v2/scolarite/inscriptions/{$id}");
        }
    }

    // ─── Changement de classe ────────────────────────────────────────────────

    public function changerClasse(string $id): void
    {
        $this->requirePermission('inscriptions.update');
        $this->verifyCsrf();

        $nouvelleClasseId = (int)($_POST['nouvelle_classe_id'] ?? 0);

        if (!$nouvelleClasseId) {
            Session::flash('error', "Nouvelle classe non sélectionnée.");
            $this->redirect("/v2/scolarite/inscriptions/{$id}");
            return;
        }

        try {
            $this->service->changerClasse((int)$id, $nouvelleClasseId, Session::getUser()['id']);
            Session::flash('success', "Élève transféré dans la nouvelle classe.");
        } catch (\RuntimeException $e) {
            Session::flash('error', $e->getMessage());
        }

        $this->redirect("/v2/scolarite/inscriptions/{$id}");
    }
}

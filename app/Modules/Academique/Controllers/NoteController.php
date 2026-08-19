<?php

namespace App\Modules\Academique\Controllers;

use App\Modules\Academique\DTO\NoteBatchDTO;
use App\Modules\Academique\DTO\NoteDTO;
use App\Modules\Academique\DTO\NoteFiltersDTO;
use App\Modules\Academique\Models\EvaluationModel;
use App\Modules\Academique\Models\NoteModel;
use App\Modules\Academique\Policies\NotePolicy;
use App\Modules\Academique\Repositories\NoteRepository;
use App\Modules\Academique\Repositories\EvaluationRepository;
use App\Modules\Academique\Services\NoteService;
use Core\Controller;
use Core\Session;

class NoteController extends Controller
{
    private NoteRepository      $repo;
    private NoteService         $service;
    private NotePolicy          $policy;
    private EvaluationModel     $evalModel;
    private EvaluationRepository $evalRepo;

    public function __construct()
    {
        parent::__construct();
        $this->repo      = new NoteRepository();
        $this->service   = new NoteService();
        $this->policy    = new NotePolicy();
        $this->evalModel = new EvaluationModel();
        $this->evalRepo  = new EvaluationRepository();
    }

    // ─── Index : toutes les notes d'une évaluation ───────────────────────────

    public function index(string $id): void
    {
        $this->requirePermission('academique.notes.view');

        $evaluation = $this->loadEvaluation((int)$id);
        $filters    = NoteFiltersDTO::fromRequest(
            array_merge($_GET, ['evaluation_id' => $id])
        );
        $pagination = $this->repo->paginate($filters->toArray(), $filters->page, $filters->perPage);
        $stats      = $this->repo->countStats((int)$id);
        $user       = $this->currentUser();

        $this->render('Academique::notes/index', [
            'title'      => 'Notes — ' . $evaluation->libelle,
            'evaluation' => $evaluation,
            'pagination' => $pagination,
            'stats'      => $stats,
            'nbEleves'   => $this->repo->countElevesInClasse((int)$id),
            'filters'    => $filters,
            'statuts'    => NoteModel::STATUT_LABELS,
            'colors'     => NoteModel::STATUT_COLORS,
            'policy'     => $this->policy,
            'user'       => $user,
        ]);
    }

    // ─── Saisie batch ────────────────────────────────────────────────────────

    public function saisie(string $id): void
    {
        $this->requirePermission('academique.notes.manage');

        $evaluation = $this->loadEvaluation((int)$id);
        $user       = $this->currentUser();

        if (!$this->policy->canSaisir($user, $evaluation)) {
            Session::flash('error', "La saisie de notes est fermée pour cette évaluation.");
            $this->redirect(BASE_URL . '/v2/academique/evaluations/' . $id);
        }

        $elevesAvecNotes = $this->repo->listElevesAvecNotes((int)$id);

        $this->render('Academique::notes/saisie', [
            'title'          => 'Saisie des notes — ' . $evaluation->libelle,
            'evaluation'     => $evaluation,
            'elevesAvecNotes'=> $elevesAvecNotes,
            'statuts'        => NoteModel::STATUT_LABELS,
            'colors'         => NoteModel::STATUT_COLORS,
            'policy'         => $this->policy,
            'user'           => $user,
        ]);
    }

    public function store(string $id): void
    {
        $this->requirePermission('academique.notes.manage');
        $this->verifyCsrf();

        $evaluation = $this->loadEvaluation((int)$id);
        $user       = $this->currentUser();

        if (!$this->policy->canSaisir($user, $evaluation)) {
            Session::flash('error', "La saisie est fermée.");
            $this->redirect(BASE_URL . '/v2/academique/evaluations/' . $id . '/notes');
        }

        $batch = NoteBatchDTO::fromRequest((int)$id, $_POST);

        try {
            $results = $this->service->saisirBatch($batch, (int)$user['id']);
            $msg     = "{$results['created']} note(s) créée(s), {$results['updated']} mise(s) à jour.";
            if (!empty($results['errors'])) {
                $msg .= " " . count($results['errors']) . " erreur(s).";
            }
            Session::flash('success', $msg);
        } catch (\RuntimeException $e) {
            Session::flash('error', $e->getMessage());
        }

        $this->redirect(BASE_URL . '/v2/academique/evaluations/' . $id . '/notes');
    }

    // ─── Publier toutes les notes ─────────────────────────────────────────────

    public function publier(string $id): void
    {
        $this->requirePermission('academique.notes.manage');
        $this->verifyCsrf();

        try {
            $user  = $this->currentUser();
            $count = $this->service->publierTout((int)$id, (int)$user['id']);
            Session::flash('success', "{$count} note(s) publiée(s).");
        } catch (\RuntimeException $e) {
            Session::flash('error', $e->getMessage());
        }

        $this->redirect(BASE_URL . '/v2/academique/evaluations/' . $id . '/notes');
    }

    // ─── Verrouiller toutes les notes ────────────────────────────────────────

    public function verrouiller(string $id): void
    {
        $this->requirePermission('academique.notes.admin');
        $this->verifyCsrf();

        try {
            $user  = $this->currentUser();
            $count = $this->service->verrouillerTout((int)$id, (int)$user['id']);
            Session::flash('success', "{$count} note(s) verrouillée(s).");
        } catch (\RuntimeException $e) {
            Session::flash('error', $e->getMessage());
        }

        $this->redirect(BASE_URL . '/v2/academique/evaluations/' . $id . '/notes');
    }

    // ─── Import CSV ──────────────────────────────────────────────────────────

    public function importerForm(string $id): void
    {
        $this->requirePermission('academique.notes.manage');

        $evaluation = $this->loadEvaluation((int)$id);
        $user       = $this->currentUser();

        if (!$this->policy->canImporter($user, $evaluation)) {
            Session::flash('error', "Import impossible : saisie fermée.");
            $this->redirect(BASE_URL . '/v2/academique/evaluations/' . $id . '/notes');
        }

        $this->render('Academique::notes/importer', [
            'title'      => 'Importer des notes — ' . $evaluation->libelle,
            'evaluation' => $evaluation,
            'user'       => $user,
        ]);
    }

    public function importerCsv(string $id): void
    {
        $this->requirePermission('academique.notes.manage');
        $this->verifyCsrf();

        $evaluation = $this->loadEvaluation((int)$id);
        $user       = $this->currentUser();

        if (!$this->policy->canImporter($user, $evaluation)) {
            Session::flash('error', "Import impossible : saisie fermée.");
            $this->redirect(BASE_URL . '/v2/academique/evaluations/' . $id . '/notes');
        }

        if (empty($_FILES['csv']['tmp_name']) || !is_uploaded_file($_FILES['csv']['tmp_name'])) {
            Session::flash('error', "Aucun fichier CSV sélectionné.");
            $this->redirect(BASE_URL . '/v2/academique/evaluations/' . $id . '/notes/importer');
        }

        $ext = strtolower(pathinfo($_FILES['csv']['name'], PATHINFO_EXTENSION));
        if ($ext !== 'csv') {
            Session::flash('error', "Le fichier doit être au format CSV.");
            $this->redirect(BASE_URL . '/v2/academique/evaluations/' . $id . '/notes/importer');
        }

        try {
            $content = file_get_contents($_FILES['csv']['tmp_name']);
            $results = $this->service->importerCsv((int)$id, $content, (int)$user['id']);
            $msg = "{$results['created']} créée(s), {$results['updated']} mise(s) à jour";
            if (!empty($results['errors'])) $msg .= ", " . count($results['errors']) . " erreur(s)";
            Session::flash('success', "Import terminé : {$msg}.");
        } catch (\RuntimeException $e) {
            Session::flash('error', $e->getMessage());
        }

        $this->redirect(BASE_URL . '/v2/academique/evaluations/' . $id . '/notes');
    }

    // ─── Fiche note individuelle ──────────────────────────────────────────────

    public function show(string $id): void
    {
        $this->requirePermission('academique.notes.view');

        $note = $this->repo->findWithDetails((int)$id);
        if (!$note) {
            Session::flash('error', "Note introuvable.");
            $this->redirect(BASE_URL . '/v2/academique/evaluations');
        }

        $historique = $this->repo->getHistorique((int)$id);
        $user       = $this->currentUser();

        $this->render('Academique::notes/show', [
            'title'      => 'Note — ' . $note->eleve_prenom . ' ' . $note->eleve_nom,
            'note'       => $note,
            'historique' => $historique,
            'statuts'    => NoteModel::STATUT_LABELS,
            'colors'     => NoteModel::STATUT_COLORS,
            'icons'      => NoteModel::STATUT_ICONS,
            'policy'     => $this->policy,
            'user'       => $user,
        ]);
    }

    public function update(string $id): void
    {
        $this->requirePermission('academique.notes.manage');
        $this->verifyCsrf();

        $note = $this->repo->findWithDetails((int)$id);
        if (!$note) {
            Session::flash('error', "Note introuvable.");
            $this->redirect(BASE_URL . '/v2/academique/evaluations');
        }

        $user = $this->currentUser();
        if (!$this->policy->canModifier($user, $note)) {
            Session::flash('error', "Cette note est verrouillée.");
            $this->redirect(BASE_URL . '/v2/academique/notes/' . $id);
        }

        $dto = NoteDTO::fromRequest(array_merge($_POST, [
            'evaluation_id' => $note->evaluation_id,
            'eleve_id'      => $note->eleve_id,
        ]));

        try {
            $this->service->modifier((int)$id, $dto, (int)$user['id']);
            Session::flash('success', "Note mise à jour.");
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            Session::flash('error', $e->getMessage());
        }

        $this->redirect(BASE_URL . '/v2/academique/notes/' . $id);
    }

    // ─── Listing notes by eleve / filters (legacy compatibility for /notes) ──

    public function listForEleve(): void
    {
        $this->requirePermission('academique.notes.view');

        $filters    = NoteFiltersDTO::fromRequest($_GET);
        $page       = $filters->page ?? 1;
        $perPage    = $filters->perPage ?? 50;
        $pagination = $this->repo->paginate($filters->toArray(), $page, $perPage);
        $user       = $this->currentUser();

        $eleve = null;
        if (!empty($filters->eleve_id)) {
            $eleveModel = new \App\Models\EleveModel();
            $eleve = $eleveModel->findWithDetails((int)$filters->eleve_id) ?: null;
        }

        $this->render('Academique::notes/eleve', [
            'title'      => 'Notes',
            'pagination' => $pagination,
            'filters'    => $filters,
            'statuts'    => NoteModel::STATUT_LABELS,
            'colors'     => NoteModel::STATUT_COLORS,
            'user'       => $user,
            'eleve'      => $eleve,
        ]);
    }

    // ─── Privé ───────────────────────────────────────────────────────────────

    private function loadEvaluation(int $id): object
    {
        $ev = $this->evalRepo->findWithDetails($id);
        if (!$ev) {
            Session::flash('error', "Évaluation introuvable.");
            $this->redirect(BASE_URL . '/v2/academique/evaluations');
        }
        return $ev;
    }
}

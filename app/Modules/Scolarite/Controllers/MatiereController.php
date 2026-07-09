<?php

namespace App\Modules\Scolarite\Controllers;

use Core\Controller;
use Core\Session;
use App\Modules\Scolarite\DTO\MatiereDTO;
use App\Modules\Scolarite\DTO\MatiereFiltersDTO;
use App\Modules\Scolarite\Models\MatiereModel;
use App\Modules\Scolarite\Repositories\MatiereRepository;
use App\Modules\Scolarite\Services\MatiereService;
use App\Modules\Scolarite\Policies\MatierePolicy;
use App\Models\ClasseModel as V1ClasseModel;
use App\Models\ProfesseurModel;

class MatiereController extends Controller
{
    private MatiereRepository $repo;
    private MatiereService    $service;
    private MatierePolicy     $policy;

    public function __construct()
    {
        parent::__construct();
        $this->repo    = new MatiereRepository();
        $this->service = new MatiereService($this->repo);
        $this->policy  = new MatierePolicy();
    }

    // ─── Liste ────────────────────────────────────────────────────────────────

    /** GET /v2/scolarite/matieres */
    public function index(): void
    {
        $this->requirePermission('matieres.view');

        $filters  = MatiereFiltersDTO::fromRequest($_GET);
        $result   = $this->repo->paginate(
            $filters->q, $filters->actif, $filters->filiere, $filters->niveau,
            $filters->page, $filters->perPage
        );
        $stats    = $this->repo->countStats();
        $filieres = $this->repo->getFilieres();
        $niveaux  = V1ClasseModel::NIVEAUX;
        $user     = $this->currentUser();

        $this->render('Scolarite::matieres/index', [
            'title'    => 'Référentiel pédagogique',
            'result'   => $result,
            'filters'  => $filters,
            'stats'    => $stats,
            'filieres' => $filieres,
            'niveaux'  => $niveaux,
            'canCreate'=> $this->policy->canCreate($user),
            'canUpdate'=> $this->policy->canUpdate($user),
            'canDelete'=> $this->policy->canDelete($user),
        ]);
    }

    // ─── Détail ───────────────────────────────────────────────────────────────

    /** GET /v2/scolarite/matieres/{id} */
    public function show(string $id): void
    {
        $this->requirePermission('matieres.view');

        $matiere = $this->repo->findWithDetails((int)$id);
        if (!$matiere) {
            Session::flash('error', 'Matière introuvable.');
            $this->redirect(BASE_URL . '/v2/scolarite/matieres');
            return;
        }

        $enseignements = $this->repo->getEnseignements((int)$id);
        $model         = new MatiereModel();
        $niveauxArray  = $model->getNiveauxArray($matiere);
        $user          = $this->currentUser();

        $this->render('Scolarite::matieres/show', [
            'title'         => $matiere->nom,
            'matiere'       => $matiere,
            'enseignements' => $enseignements,
            'niveauxArray'  => $niveauxArray,
            'canUpdate'     => $this->policy->canUpdate($user),
            'canArchive'    => $this->policy->canArchive($user),
            'canDelete'     => $this->policy->canDelete($user),
        ]);
    }

    // ─── Formulaire création ──────────────────────────────────────────────────

    /** GET /v2/scolarite/matieres/create */
    public function create(): void
    {
        $this->requirePermission('matieres.create');

        $this->render('Scolarite::matieres/form', [
            'title'      => 'Nouvelle matière',
            'matiere'    => null,
            'isEdit'     => false,
            'niveaux'    => V1ClasseModel::NIVEAUX,
            'filieres'   => MatiereDTO::FILIERES,
            'couleurs'   => MatiereModel::COULEURS_PRESET,
            'professeurs'=> (new ProfesseurModel())->findForSelect(),
            'errors'     => [],
            'old'        => [],
        ]);
    }

    // ─── Enregistrer ──────────────────────────────────────────────────────────

    /** POST /v2/scolarite/matieres */
    public function store(): void
    {
        $this->requirePermission('matieres.create');
        $this->verifyCsrf();

        $dto    = MatiereDTO::fromRequest($_POST);
        $errors = $dto->validate();

        if (!empty($errors)) {
            $this->render('Scolarite::matieres/form', [
                'title'      => 'Nouvelle matière',
                'matiere'    => null,
                'isEdit'     => false,
                'niveaux'    => V1ClasseModel::NIVEAUX,
                'filieres'   => MatiereDTO::FILIERES,
                'couleurs'   => MatiereModel::COULEURS_PRESET,
                'professeurs'=> (new ProfesseurModel())->findForSelect(),
                'errors'     => $errors,
                'old'        => $_POST,
            ]);
            return;
        }

        try {
            $user      = $this->currentUser();
            $matiereId = $this->service->creer($dto, (int)$user['id']);
            Session::flash('success', "Matière « {$dto->nom} » créée avec succès.");
            $this->redirect(BASE_URL . '/v2/scolarite/matieres/' . $matiereId);
        } catch (\RuntimeException $e) {
            $this->render('Scolarite::matieres/form', [
                'title'      => 'Nouvelle matière',
                'matiere'    => null,
                'isEdit'     => false,
                'niveaux'    => V1ClasseModel::NIVEAUX,
                'filieres'   => MatiereDTO::FILIERES,
                'couleurs'   => MatiereModel::COULEURS_PRESET,
                'professeurs'=> (new ProfesseurModel())->findForSelect(),
                'errors'     => ['global' => $e->getMessage()],
                'old'        => $_POST,
            ]);
        }
    }

    // ─── Formulaire édition ───────────────────────────────────────────────────

    /** GET /v2/scolarite/matieres/{id}/edit */
    public function edit(string $id): void
    {
        $this->requirePermission('matieres.update');

        $model   = new MatiereModel();
        $matiere = $model->findById((int)$id);
        if (!$matiere) {
            Session::flash('error', 'Matière introuvable.');
            $this->redirect(BASE_URL . '/v2/scolarite/matieres');
            return;
        }

        $this->render('Scolarite::matieres/form', [
            'title'      => 'Modifier — ' . $matiere->nom,
            'matiere'    => $matiere,
            'isEdit'     => true,
            'niveaux'    => V1ClasseModel::NIVEAUX,
            'filieres'   => MatiereDTO::FILIERES,
            'couleurs'   => MatiereModel::COULEURS_PRESET,
            'professeurs'=> (new ProfesseurModel())->findForSelect(),
            'errors'     => [],
            'old'        => [],
        ]);
    }

    // ─── Mettre à jour ────────────────────────────────────────────────────────

    /** POST /v2/scolarite/matieres/{id} */
    public function update(string $id): void
    {
        $this->requirePermission('matieres.update');
        $this->verifyCsrf();

        $model   = new MatiereModel();
        $matiere = $model->findById((int)$id);
        if (!$matiere) {
            Session::flash('error', 'Matière introuvable.');
            $this->redirect(BASE_URL . '/v2/scolarite/matieres');
            return;
        }

        $dto    = MatiereDTO::fromRequest($_POST);
        $errors = $dto->validate();

        if (!empty($errors)) {
            $this->render('Scolarite::matieres/form', [
                'title'      => 'Modifier — ' . $matiere->nom,
                'matiere'    => $matiere,
                'isEdit'     => true,
                'niveaux'    => V1ClasseModel::NIVEAUX,
                'filieres'   => MatiereDTO::FILIERES,
                'couleurs'   => MatiereModel::COULEURS_PRESET,
                'professeurs'=> (new ProfesseurModel())->findForSelect(),
                'errors'     => $errors,
                'old'        => $_POST,
            ]);
            return;
        }

        try {
            $user = $this->currentUser();
            $this->service->modifier((int)$id, $dto, (int)$user['id']);
            Session::flash('success', 'Matière mise à jour.');
            $this->redirect(BASE_URL . '/v2/scolarite/matieres/' . $id);
        } catch (\RuntimeException $e) {
            Session::flash('error', $e->getMessage());
            $this->redirect(BASE_URL . '/v2/scolarite/matieres/' . $id . '/edit');
        }
    }

    // ─── Archiver ─────────────────────────────────────────────────────────────

    /** POST /v2/scolarite/matieres/{id}/archiver */
    public function archiver(string $id): void
    {
        $this->requirePermission('matieres.update');
        $this->verifyCsrf();

        try {
            $user = $this->currentUser();
            $this->service->archiver((int)$id, (int)$user['id']);
            Session::flash('success', 'Matière archivée.');
        } catch (\RuntimeException $e) {
            Session::flash('error', $e->getMessage());
        }

        $this->redirect(BASE_URL . '/v2/scolarite/matieres');
    }

    // ─── Supprimer ────────────────────────────────────────────────────────────

    /** POST /v2/scolarite/matieres/{id}/delete */
    public function destroy(string $id): void
    {
        $this->requirePermission('matieres.delete');
        $this->verifyCsrf();

        $model   = new MatiereModel();
        $matiere = $model->findById((int)$id);
        if (!$matiere) {
            $this->redirect(BASE_URL . '/v2/scolarite/matieres');
            return;
        }

        try {
            $this->service->verifierSuppression((int)$id);
            $model->delete((int)$id);
            Session::flash('success', "Matière « {$matiere->nom} » supprimée.");
        } catch (\RuntimeException $e) {
            Session::flash('error', $e->getMessage());
        }

        $this->redirect(BASE_URL . '/v2/scolarite/matieres');
    }
}

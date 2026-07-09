<?php

namespace App\Modules\Finance\Controllers;

use Core\Controller;
use Core\Session;
use App\Modules\Finance\DTO\CategorieFraisDTO;
use App\Modules\Finance\DTO\FraisTypeDTO;
use App\Modules\Finance\DTO\FraisTypeFiltersDTO;
use App\Modules\Finance\Models\CategorieFraisModel;
use App\Modules\Finance\Models\FraisTypeModel;
use App\Modules\Finance\Policies\FraisPolicy;
use App\Modules\Finance\Repositories\FraisRepository;
use App\Modules\Finance\Services\FraisService;

class FraisController extends Controller
{
    private FraisRepository $repo;
    private FraisService    $service;
    private FraisPolicy     $policy;

    public function __construct()
    {
        parent::__construct();
        $this->repo    = new FraisRepository();
        $this->service = new FraisService($this->repo);
        $this->policy  = new FraisPolicy();
    }

    // ─── Liste des types de frais ─────────────────────────────────────────────

    /** GET /v2/finance/frais */
    public function index(): void
    {
        $this->requirePermission('finance.frais.view');

        $user    = $this->currentUser();
        $filters = FraisTypeFiltersDTO::fromRequest($_GET);
        $result  = $this->repo->paginate(
            $filters->q, $filters->statut, $filters->periodicite,
            $filters->estObligatoire, $filters->categorieId, $filters->anneeScolaire,
            $filters->niveau, $filters->page, $filters->perPage
        );
        $stats      = $this->repo->countStats();
        $categories = (new CategorieFraisModel())->findActives();

        $this->render('Finance::frais/index', [
            'title'      => 'Référentiel des frais',
            'result'     => $result,
            'filters'    => $filters,
            'stats'      => $stats,
            'categories' => $categories,
            'periodicites'=> FraisTypeDTO::PERIODICITES,
            'canCreate'  => $this->policy->canCreate($user),
            'canUpdate'  => $this->policy->canUpdate($user),
            'canArchive' => $this->policy->canArchive($user),
            'canDelete'  => $this->policy->canDelete($user),
            'canTarifs'  => $this->policy->canManageTarifs($user),
        ]);
    }

    // ─── Détail d'un frais ────────────────────────────────────────────────────

    /** GET /v2/finance/frais/{id} */
    public function show(string $id): void
    {
        $this->requirePermission('finance.frais.view');

        $frais = $this->repo->findWithDetails((int)$id);
        if (!$frais) {
            Session::flash('error', 'Type de frais introuvable.');
            $this->redirect(BASE_URL . '/v2/finance/frais');
            return;
        }

        $user      = $this->currentUser();
        $tarifs    = $this->repo->getTarifs((int)$id);
        $historique = $this->repo->getHistorique((int)$id);
        $model     = new FraisTypeModel();

        $this->render('Finance::frais/show', [
            'title'      => $frais->nom,
            'frais'      => $frais,
            'niveaux'    => $model->getNiveauxArray($frais),
            'tarifs'     => $tarifs,
            'historique' => $historique,
            'canUpdate'  => $this->policy->canUpdate($user),
            'canArchive' => $this->policy->canArchive($user),
            'canDelete'  => $this->policy->canDelete($user),
            'canTarifs'  => $this->policy->canManageTarifs($user),
        ]);
    }

    // ─── Formulaire création ──────────────────────────────────────────────────

    /** GET /v2/finance/frais/create */
    public function create(): void
    {
        $this->requirePermission('finance.frais.manage');

        $this->render('Finance::frais/form', [
            'title'       => 'Nouveau type de frais',
            'frais'       => null,
            'isEdit'      => false,
            'categories'  => (new CategorieFraisModel())->findActives(),
            'periodicites'=> FraisTypeDTO::PERIODICITES,
            'devises'     => FraisTypeDTO::DEVISES,
            'errors'      => [],
            'old'         => [],
        ]);
    }

    // ─── Enregistrer ──────────────────────────────────────────────────────────

    /** POST /v2/finance/frais */
    public function store(): void
    {
        $this->requirePermission('finance.frais.manage');
        $this->verifyCsrf();

        $dto    = FraisTypeDTO::fromRequest($_POST);
        $errors = $dto->validate();

        if (!empty($errors)) {
            $this->renderForm(null, $dto, $errors, false);
            return;
        }

        try {
            $user        = $this->currentUser();
            $fraisTypeId = $this->service->creer($dto, (int)$user['id']);
            Session::flash('success', "Frais « {$dto->nom} » créé avec succès.");
            $this->redirect(BASE_URL . '/v2/finance/frais/' . $fraisTypeId);
        } catch (\RuntimeException $e) {
            $this->renderForm(null, $dto, ['global' => $e->getMessage()], false);
        }
    }

    // ─── Formulaire édition ───────────────────────────────────────────────────

    /** GET /v2/finance/frais/{id}/edit */
    public function edit(string $id): void
    {
        $this->requirePermission('finance.frais.manage');

        $model = new FraisTypeModel();
        $frais = $model->findById((int)$id);
        if (!$frais) {
            Session::flash('error', 'Type de frais introuvable.');
            $this->redirect(BASE_URL . '/v2/finance/frais');
            return;
        }

        if ($frais->statut === 'archive') {
            Session::flash('error', 'Un frais archivé ne peut pas être modifié.');
            $this->redirect(BASE_URL . '/v2/finance/frais/' . $id);
            return;
        }

        $this->render('Finance::frais/form', [
            'title'       => 'Modifier — ' . $frais->nom,
            'frais'       => $frais,
            'isEdit'      => true,
            'categories'  => (new CategorieFraisModel())->findActives(),
            'periodicites'=> FraisTypeDTO::PERIODICITES,
            'devises'     => FraisTypeDTO::DEVISES,
            'errors'      => [],
            'old'         => [],
        ]);
    }

    // ─── Mettre à jour ────────────────────────────────────────────────────────

    /** POST /v2/finance/frais/{id} */
    public function update(string $id): void
    {
        $this->requirePermission('finance.frais.manage');
        $this->verifyCsrf();

        $model = new FraisTypeModel();
        $frais = $model->findById((int)$id);
        if (!$frais) {
            Session::flash('error', 'Type de frais introuvable.');
            $this->redirect(BASE_URL . '/v2/finance/frais');
            return;
        }

        $dto    = FraisTypeDTO::fromRequest($_POST);
        $errors = $dto->validate();

        if (!empty($errors)) {
            $this->renderForm($frais, $dto, $errors, true);
            return;
        }

        try {
            $user = $this->currentUser();
            $this->service->modifier((int)$id, $dto, (int)$user['id']);
            Session::flash('success', 'Frais mis à jour avec succès.');
            $this->redirect(BASE_URL . '/v2/finance/frais/' . $id);
        } catch (\RuntimeException $e) {
            Session::flash('error', $e->getMessage());
            $this->redirect(BASE_URL . '/v2/finance/frais/' . $id . '/edit');
        }
    }

    // ─── Activer ──────────────────────────────────────────────────────────────

    /** POST /v2/finance/frais/{id}/activer */
    public function activer(string $id): void
    {
        $this->requirePermission('finance.frais.manage');
        $this->verifyCsrf();

        try {
            $user = $this->currentUser();
            $this->service->activer((int)$id, (int)$user['id']);
            Session::flash('success', 'Frais activé.');
        } catch (\RuntimeException $e) {
            Session::flash('error', $e->getMessage());
        }

        $this->redirect(BASE_URL . '/v2/finance/frais/' . $id);
    }

    // ─── Désactiver ───────────────────────────────────────────────────────────

    /** POST /v2/finance/frais/{id}/desactiver */
    public function desactiver(string $id): void
    {
        $this->requirePermission('finance.frais.manage');
        $this->verifyCsrf();

        try {
            $user = $this->currentUser();
            $this->service->desactiver((int)$id, (int)$user['id']);
            Session::flash('success', 'Frais désactivé.');
        } catch (\RuntimeException $e) {
            Session::flash('error', $e->getMessage());
        }

        $this->redirect(BASE_URL . '/v2/finance/frais/' . $id);
    }

    // ─── Archiver ─────────────────────────────────────────────────────────────

    /** POST /v2/finance/frais/{id}/archiver */
    public function archiver(string $id): void
    {
        $this->requirePermission('finance.frais.admin');
        $this->verifyCsrf();

        $motif = trim($_POST['motif'] ?? '');
        if ($motif === '') {
            Session::flash('error', "Un motif d'archivage est requis.");
            $this->redirect(BASE_URL . '/v2/finance/frais/' . $id);
            return;
        }

        try {
            $user = $this->currentUser();
            $this->service->archiver((int)$id, $motif, (int)$user['id']);
            Session::flash('success', 'Frais archivé. Il reste consultable dans l\'historique.');
        } catch (\RuntimeException $e) {
            Session::flash('error', $e->getMessage());
        }

        $this->redirect(BASE_URL . '/v2/finance/frais');
    }

    // ─── Supprimer ────────────────────────────────────────────────────────────

    /** POST /v2/finance/frais/{id}/delete */
    public function destroy(string $id): void
    {
        $this->requirePermission('finance.frais.admin');
        $this->verifyCsrf();

        try {
            $user = $this->currentUser();
            $this->service->supprimer((int)$id, (int)$user['id']);
            Session::flash('success', 'Frais supprimé définitivement.');
        } catch (\RuntimeException $e) {
            Session::flash('error', $e->getMessage());
        }

        $this->redirect(BASE_URL . '/v2/finance/frais');
    }

    // ─── Tarifs ───────────────────────────────────────────────────────────────

    /** POST /v2/finance/frais/{id}/tarif */
    public function storeTarif(string $id): void
    {
        $this->requirePermission('finance.frais.manage');
        $this->verifyCsrf();

        try {
            $user = $this->currentUser();
            $this->service->definirTarif((int)$id, $_POST, (int)$user['id']);
            Session::flash('success', 'Tarif enregistré.');
        } catch (\RuntimeException $e) {
            Session::flash('error', $e->getMessage());
        }

        $this->redirect(BASE_URL . '/v2/finance/frais/' . $id);
    }

    // ─── Catégories ───────────────────────────────────────────────────────────

    /** GET /v2/finance/frais/categories */
    public function categories(): void
    {
        $this->requirePermission('finance.frais.view');

        $result = $this->repo->paginateCategories();
        $user   = $this->currentUser();

        $this->render('Finance::frais/categories', [
            'title'       => 'Catégories de frais',
            'result'      => $result,
            'canManage'   => $this->policy->canManageCategories($user),
            'couleurs'    => CategorieFraisDTO::COULEURS,
            'icones'      => CategorieFraisDTO::ICONES,
        ]);
    }

    /** POST /v2/finance/frais/categories */
    public function storeCategorie(): void
    {
        $this->requirePermission('finance.frais.manage');
        $this->verifyCsrf();

        $dto    = CategorieFraisDTO::fromRequest($_POST);
        $errors = $dto->validate();

        if (!empty($errors)) {
            Session::flash('error', implode(' ', $errors));
            $this->redirect(BASE_URL . '/v2/finance/frais/categories');
            return;
        }

        try {
            $user = $this->currentUser();
            $this->service->creerCategorie($dto, (int)$user['id']);
            Session::flash('success', "Catégorie « {$dto->nom} » créée.");
        } catch (\RuntimeException $e) {
            Session::flash('error', $e->getMessage());
        }

        $this->redirect(BASE_URL . '/v2/finance/frais/categories');
    }

    /** POST /v2/finance/frais/categories/{id} */
    public function updateCategorie(string $id): void
    {
        $this->requirePermission('finance.frais.manage');
        $this->verifyCsrf();

        $dto    = CategorieFraisDTO::fromRequest($_POST);
        $errors = $dto->validate();

        if (!empty($errors)) {
            Session::flash('error', implode(' ', $errors));
            $this->redirect(BASE_URL . '/v2/finance/frais/categories');
            return;
        }

        try {
            $user = $this->currentUser();
            $this->service->modifierCategorie((int)$id, $dto, (int)$user['id']);
            Session::flash('success', 'Catégorie mise à jour.');
        } catch (\RuntimeException $e) {
            Session::flash('error', $e->getMessage());
        }

        $this->redirect(BASE_URL . '/v2/finance/frais/categories');
    }

    /** POST /v2/finance/frais/categories/{id}/toggle */
    public function toggleCategorie(string $id): void
    {
        $this->requirePermission('finance.frais.manage');
        $this->verifyCsrf();

        try {
            $user = $this->currentUser();
            $this->service->toggleCategorie((int)$id, (int)$user['id']);
            Session::flash('success', 'Statut de la catégorie mis à jour.');
        } catch (\RuntimeException $e) {
            Session::flash('error', $e->getMessage());
        }

        $this->redirect(BASE_URL . '/v2/finance/frais/categories');
    }

    // ─── Helper ───────────────────────────────────────────────────────────────

    private function renderForm(
        ?object       $frais,
        FraisTypeDTO  $dto,
        array         $errors,
        bool          $isEdit
    ): void {
        $this->render('Finance::frais/form', [
            'title'       => $isEdit ? 'Modifier — ' . $frais->nom : 'Nouveau type de frais',
            'frais'       => $frais,
            'isEdit'      => $isEdit,
            'categories'  => (new CategorieFraisModel())->findActives(),
            'periodicites'=> FraisTypeDTO::PERIODICITES,
            'devises'     => FraisTypeDTO::DEVISES,
            'errors'      => $errors,
            'old'         => $_POST,
        ]);
    }
}

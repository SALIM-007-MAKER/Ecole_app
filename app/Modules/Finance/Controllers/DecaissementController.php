<?php

namespace App\Modules\Finance\Controllers;

use App\Modules\Finance\DTO\CategorieDepenseDTO;
use App\Modules\Finance\DTO\DecaissementDTO;
use App\Modules\Finance\DTO\DecaissementFiltersDTO;
use App\Modules\Finance\DTO\FournisseurDTO;
use App\Modules\Finance\Models\DecaissementModel;
use App\Modules\Finance\Policies\DecaissementPolicy;
use App\Modules\Finance\Repositories\DecaissementRepository;
use App\Modules\Finance\Services\DecaissementService;
use Core\Controller;
use Core\Session;

class DecaissementController extends Controller
{
    private DecaissementService    $service;
    private DecaissementRepository $repo;
    private DecaissementPolicy     $policy;

    public function __construct()
    {
        parent::__construct();
        $this->service = new DecaissementService();
        $this->repo    = new DecaissementRepository();
        $this->policy  = new DecaissementPolicy();
    }

    // GET /v2/finance/decaissements
    public function index(): void
    {
        $user = $this->currentUser();
        if (!$this->policy->canView($user)) {
            http_response_code(403);
            $this->render('errors/403', ['title' => 'Accès non autorisé'], 'none');
            return;
        }

        $filters = DecaissementFiltersDTO::fromRequest($_GET);
        $result  = $this->repo->paginate($filters);
        $stats   = $this->repo->statsGlobal();

        $this->render('Finance::decaissements/index', [
            'title'      => 'Décaissements',
            'result'     => $result,
            'filters'    => $filters,
            'stats'      => $stats,
            'statuts'    => DecaissementModel::STATUTS,
            'categories' => $this->repo->findAllCategories(true),
            'canCreate'  => $this->policy->canCreate($user),
        ]);
    }

    // GET /v2/finance/decaissements/create
    public function create(): void
    {
        $user = $this->currentUser();
        if (!$this->policy->canCreate($user)) {
            $this->redirect('/v2/finance/decaissements');
            return;
        }

        $this->render('Finance::decaissements/form', [
            'title'       => 'Nouveau décaissement',
            'errors'      => [],
            'old'         => [],
            'categories'  => $this->repo->findAllCategories(true),
            'fournisseurs' => $this->repo->findAllFournisseurs(true),
        ]);
    }

    // POST /v2/finance/decaissements
    public function store(): void
    {
        $user = $this->currentUser();
        if (!$this->policy->canCreate($user)) {
            Session::flash('error', 'Accès refusé.');
            $this->redirect('/v2/finance/decaissements');
            return;
        }
        $this->verifyCsrf();

        $dto = DecaissementDTO::fromRequest($_POST);
        try {
            $decId = $this->service->soumettre($dto, (int)$user['id']);
            Session::flash('success', 'Décaissement soumis avec succès.');
            $this->redirect('/v2/finance/decaissements/' . $decId);
        } catch (\Throwable $e) {
            $this->render('Finance::decaissements/form', [
                'title'        => 'Nouveau décaissement',
                'errors'       => ['global' => $e->getMessage()],
                'old'          => $_POST,
                'categories'   => $this->repo->findAllCategories(true),
                'fournisseurs' => $this->repo->findAllFournisseurs(true),
            ]);
        }
    }

    // GET /v2/finance/decaissements/{id}
    public function show(int $id): void
    {
        $user = $this->currentUser();
        if (!$this->policy->canView($user)) {
            http_response_code(403);
            $this->render('errors/403', ['title' => 'Accès non autorisé'], 'none');
            return;
        }

        $dec = $this->repo->findWithDetails($id);
        if (!$dec) {
            Session::flash('error', 'Décaissement introuvable.');
            $this->redirect('/v2/finance/decaissements');
            return;
        }

        $this->render('Finance::decaissements/show', [
            'title'         => 'Décaissement ' . $dec->numero,
            'dec'           => $dec,
            'justificatifs' => $this->repo->getJustificatifs($id),
            'statuts'       => DecaissementModel::STATUTS,
            'modes'         => $this->repo->findAllModes(),
            'seuil'         => $this->service->seuilApprobation(),
            'canValider'    => $this->policy->canValider($user),
            'canApprouver'  => $this->policy->canApprouver($user),
            'canRejeter'    => $this->policy->canRejeter($user),
            'canPayer'      => $this->policy->canPayer($user),
            'canAnnuler'    => $this->policy->canAnnuler($user),
        ]);
    }

    // POST /v2/finance/decaissements/{id}/valider
    public function valider(int $id): void
    {
        $user = $this->currentUser();
        if (!$this->policy->canValider($user)) {
            Session::flash('error', 'Accès refusé.');
            $this->redirect('/v2/finance/decaissements/' . $id);
            return;
        }
        $this->verifyCsrf();
        try {
            $this->service->valider($id, (int)$user['id']);
            Session::flash('success', 'Décaissement validé.');
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect('/v2/finance/decaissements/' . $id);
    }

    // POST /v2/finance/decaissements/{id}/approuver
    public function approuver(int $id): void
    {
        $user = $this->currentUser();
        if (!$this->policy->canApprouver($user)) {
            Session::flash('error', 'Accès refusé.');
            $this->redirect('/v2/finance/decaissements/' . $id);
            return;
        }
        $this->verifyCsrf();
        try {
            $this->service->approuver($id, (int)$user['id']);
            Session::flash('success', 'Décaissement approuvé.');
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect('/v2/finance/decaissements/' . $id);
    }

    // POST /v2/finance/decaissements/{id}/rejeter
    public function rejeter(int $id): void
    {
        $user = $this->currentUser();
        if (!$this->policy->canRejeter($user)) {
            Session::flash('error', 'Accès refusé.');
            $this->redirect('/v2/finance/decaissements/' . $id);
            return;
        }
        $this->verifyCsrf();
        $motif = trim($_POST['motif'] ?? '');
        try {
            $this->service->rejeter($id, (int)$user['id'], $motif);
            Session::flash('success', 'Décaissement rejeté.');
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect('/v2/finance/decaissements/' . $id);
    }

    // POST /v2/finance/decaissements/{id}/payer
    public function payer(int $id): void
    {
        $user = $this->currentUser();
        if (!$this->policy->canPayer($user)) {
            Session::flash('error', 'Accès refusé.');
            $this->redirect('/v2/finance/decaissements/' . $id);
            return;
        }
        $this->verifyCsrf();
        $mode = trim($_POST['mode_paiement'] ?? '');
        $ref  = trim($_POST['reference_externe'] ?? '') ?: null;
        try {
            $this->service->payer($id, (int)$user['id'], $mode, $ref);
            Session::flash('success', 'Décaissement payé.');
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect('/v2/finance/decaissements/' . $id);
    }

    // POST /v2/finance/decaissements/{id}/annuler
    public function annuler(int $id): void
    {
        $user = $this->currentUser();
        if (!$this->policy->canAnnuler($user)) {
            Session::flash('error', 'Accès refusé.');
            $this->redirect('/v2/finance/decaissements/' . $id);
            return;
        }
        $this->verifyCsrf();
        $motif = trim($_POST['motif'] ?? '');
        try {
            $this->service->annuler($id, (int)$user['id'], $motif);
            Session::flash('success', 'Décaissement annulé.');
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect('/v2/finance/decaissements/' . $id);
    }

    // POST /v2/finance/decaissements/{id}/justificatifs
    public function uploadJustificatif(int $id): void
    {
        $user = $this->currentUser();
        if (!$this->policy->canCreate($user)) {
            Session::flash('error', 'Accès refusé.');
            $this->redirect('/v2/finance/decaissements/' . $id);
            return;
        }
        $this->verifyCsrf();
        if (empty($_FILES['justificatif']) || ($_FILES['justificatif']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            Session::flash('error', 'Aucun fichier sélectionné.');
            $this->redirect('/v2/finance/decaissements/' . $id);
            return;
        }
        try {
            $this->service->ajouterJustificatif($id, $_FILES['justificatif'], (int)$user['id']);
            Session::flash('success', 'Justificatif ajouté.');
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect('/v2/finance/decaissements/' . $id);
    }

    // POST /v2/finance/decaissements/{id}/justificatifs/{jusId}/delete
    public function destroyJustificatif(int $id, int $jusId): void
    {
        $user = $this->currentUser();
        if (!$this->policy->canCreate($user)) {
            Session::flash('error', 'Accès refusé.');
            $this->redirect('/v2/finance/decaissements/' . $id);
            return;
        }
        $this->verifyCsrf();
        try {
            $this->service->supprimerJustificatif($jusId, $id, (int)$user['id']);
            Session::flash('success', 'Justificatif supprimé.');
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect('/v2/finance/decaissements/' . $id);
    }

    // ----------------------------------------------------------------
    // Catégories de dépenses
    // ----------------------------------------------------------------

    // GET /v2/finance/decaissements/categories
    public function categories(): void
    {
        $user = $this->currentUser();
        if (!$this->policy->canManageFournisseurs($user)) {
            http_response_code(403);
            $this->render('errors/403', ['title' => 'Accès non autorisé'], 'none');
            return;
        }
        $this->render('Finance::decaissements/categories', [
            'title'      => 'Catégories de dépenses',
            'categories' => $this->repo->findAllCategories(false),
            'couleurs'   => CategorieDepenseDTO::COULEURS,
            'icones'     => CategorieDepenseDTO::ICONES,
            'errors'     => [],
        ]);
    }

    // POST /v2/finance/decaissements/categories
    public function storeCategorie(): void
    {
        $user = $this->currentUser();
        if (!$this->policy->canManageFournisseurs($user)) {
            Session::flash('error', 'Accès refusé.');
            $this->redirect('/v2/finance/decaissements/categories');
            return;
        }
        $this->verifyCsrf();
        $dto = CategorieDepenseDTO::fromRequest($_POST);
        try {
            $this->service->creerCategorie($dto, (int)$user['id']);
            Session::flash('success', 'Catégorie créée.');
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect('/v2/finance/decaissements/categories');
    }

    // POST /v2/finance/decaissements/categories/{id}
    public function updateCategorie(int $id): void
    {
        $user = $this->currentUser();
        if (!$this->policy->canManageFournisseurs($user)) {
            Session::flash('error', 'Accès refusé.');
            $this->redirect('/v2/finance/decaissements/categories');
            return;
        }
        $this->verifyCsrf();
        $dto = CategorieDepenseDTO::fromRequest($_POST);
        try {
            $this->service->modifierCategorie($id, $dto, (int)$user['id']);
            Session::flash('success', 'Catégorie mise à jour.');
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect('/v2/finance/decaissements/categories');
    }

    // POST /v2/finance/decaissements/categories/{id}/toggle
    public function toggleCategorie(int $id): void
    {
        $user = $this->currentUser();
        if (!$this->policy->canManageFournisseurs($user)) {
            Session::flash('error', 'Accès refusé.');
            $this->redirect('/v2/finance/decaissements/categories');
            return;
        }
        $this->verifyCsrf();
        try {
            $this->service->toggleCategorie($id, (int)$user['id']);
            Session::flash('success', 'Statut de la catégorie mis à jour.');
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect('/v2/finance/decaissements/categories');
    }

    // ----------------------------------------------------------------
    // Fournisseurs
    // ----------------------------------------------------------------

    // GET /v2/finance/fournisseurs
    public function fournisseurs(): void
    {
        $user = $this->currentUser();
        if (!$this->policy->canManageFournisseurs($user)) {
            http_response_code(403);
            $this->render('errors/403', ['title' => 'Accès non autorisé'], 'none');
            return;
        }
        $page   = max(1, (int)($_GET['page'] ?? 1));
        $q      = trim((string)($_GET['q'] ?? '')) ?: null;
        $result = $this->repo->paginateFournisseurs($page, 25, $q, false);

        $this->render('Finance::decaissements/fournisseurs', [
            'title'  => 'Fournisseurs',
            'result' => $result,
            'q'      => $q,
            'errors' => [],
        ]);
    }

    // POST /v2/finance/fournisseurs
    public function storeFournisseur(): void
    {
        $user = $this->currentUser();
        if (!$this->policy->canManageFournisseurs($user)) {
            Session::flash('error', 'Accès refusé.');
            $this->redirect('/v2/finance/fournisseurs');
            return;
        }
        $this->verifyCsrf();
        $dto = FournisseurDTO::fromRequest($_POST);
        try {
            $this->service->creerFournisseur($dto, (int)$user['id']);
            Session::flash('success', 'Fournisseur créé.');
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect('/v2/finance/fournisseurs');
    }

    // POST /v2/finance/fournisseurs/{id}
    public function updateFournisseur(int $id): void
    {
        $user = $this->currentUser();
        if (!$this->policy->canManageFournisseurs($user)) {
            Session::flash('error', 'Accès refusé.');
            $this->redirect('/v2/finance/fournisseurs');
            return;
        }
        $this->verifyCsrf();
        $dto = FournisseurDTO::fromRequest($_POST);
        try {
            $this->service->modifierFournisseur($id, $dto, (int)$user['id']);
            Session::flash('success', 'Fournisseur mis à jour.');
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect('/v2/finance/fournisseurs');
    }

    // POST /v2/finance/fournisseurs/{id}/toggle
    public function toggleFournisseur(int $id): void
    {
        $user = $this->currentUser();
        if (!$this->policy->canManageFournisseurs($user)) {
            Session::flash('error', 'Accès refusé.');
            $this->redirect('/v2/finance/fournisseurs');
            return;
        }
        $this->verifyCsrf();
        try {
            $this->service->toggleFournisseur($id, (int)$user['id']);
            Session::flash('success', 'Statut du fournisseur mis à jour.');
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect('/v2/finance/fournisseurs');
    }
}

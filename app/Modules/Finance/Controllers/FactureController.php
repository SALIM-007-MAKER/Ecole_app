<?php

namespace App\Modules\Finance\Controllers;

use Core\Controller;
use App\Modules\Finance\DTO\InvoiceDTO;
use App\Modules\Finance\DTO\InvoiceFiltersDTO;
use App\Modules\Finance\DTO\LigneFactureDTO;
use App\Modules\Finance\DTO\PaymentFiltersDTO;
use App\Modules\Finance\DTO\RemiseDTO;
use App\Modules\Finance\Policies\InvoicePolicy;
use App\Modules\Finance\Repositories\InvoiceRepository;
use App\Modules\Finance\Repositories\PaymentRepository;
use App\Modules\Finance\Services\InvoiceService;
use App\Models\ClasseModel;
use App\Models\EleveModel;
use App\Shared\Auth\EleveScopeTrait;

class FactureController extends Controller
{
    use EleveScopeTrait;

    private InvoiceService    $service;
    private InvoiceRepository $repo;
    private InvoicePolicy     $policy;
    private PaymentRepository $paymentRepo;

    public function __construct()
    {
        parent::__construct();
        $this->service     = new InvoiceService();
        $this->repo        = new InvoiceRepository();
        $this->policy      = new InvoicePolicy();
        $this->paymentRepo = new PaymentRepository();
    }

    // GET /v2/finance/mes-paiements — espace parent/élève : scolarité et paiements de l'enfant
    public function mesPaiements(): void
    {
        $this->requireAuth();
        $user  = $this->currentUser();
        $scope = $this->myEleveIds();

        // Rôle staff (non restreint par EleveScopeTrait) : cette page ne le concerne pas.
        if ($scope === null) {
            $this->redirect(BASE_URL . '/v2/finance/factures');
            return;
        }

        if (!in_array('finance.paiements.view.own', $user['permissions'] ?? [], true)) {
            http_response_code(403);
            $this->render('errors/403', ['title' => 'Accès non autorisé'], 'none');
            return;
        }

        $eleveModel = new EleveModel();
        $enfants    = $user['role'] === 'parent'
            ? $eleveModel->findByParent((int)$user['id'])
            : array_values(array_filter([$eleveModel->findById($scope[0] ?? 0) ?: null]));

        $eleveId = (int)($_GET['eleve_id'] ?? ($scope[0] ?? 0));
        if (!in_array($eleveId, $scope, true)) {
            $eleveId = $scope[0] ?? 0;
        }
        $annee = trim((string)($_GET['annee_scolaire'] ?? '')) ?: (date('n') >= 9
            ? date('Y') . '-' . (date('Y') + 1)
            : (date('Y') - 1) . '-' . date('Y'));

        $factures  = [];
        $paiements = [];
        $totaux    = ['total_facture' => 0.0, 'total_paye' => 0.0, 'total_reste' => 0.0];

        if ($eleveId) {
            $facturesResult  = $this->repo->paginate(new InvoiceFiltersDTO(
                anneeScolaire: $annee, eleveId: $eleveId, perPage: 100, sortBy: 'date_emission', sortDir: 'ASC'
            ));
            $paiementsResult = $this->paymentRepo->paginate(new PaymentFiltersDTO(
                anneeScolaire: $annee, eleveId: $eleveId, perPage: 100
            ));
            $factures  = $facturesResult['data'];
            $paiements = $paiementsResult['data'];

            foreach ($factures as $f) {
                $totaux['total_facture'] += (float)$f->montant_total;
                $totaux['total_paye']    += (float)$f->montant_paye;
                $totaux['total_reste']   += max(0.0, (float)$f->montant_total - (float)$f->montant_paye);
            }
        }

        $this->render('Finance::mes_paiements', [
            'title'     => 'Scolarité et paiements',
            'enfants'   => $enfants,
            'eleveId'   => $eleveId,
            'factures'  => $factures,
            'paiements' => $paiements,
            'totaux'    => $totaux,
            'annee'     => $annee,
            'annees'    => $this->repo->getAnneesActives(),
            'isEleve'   => $user['role'] === 'eleve',
        ]);
    }

    // GET /v2/finance/factures
    public function index(): void
    {
        $user = $this->currentUser();
        $this->requirePermission('finance.factures.view');

        $filters = InvoiceFiltersDTO::fromRequest($_GET);
        $result  = $this->repo->paginate($filters);
        $stats   = $this->repo->statsGlobal($filters->anneeScolaire);
        $annees  = $this->repo->getAnneesActives();
        $classes = (new ClasseModel())->findForSelect();

        $this->render('Finance::factures/index', [
            'title'      => 'Factures',
            'result'     => $result,
            'filters'    => $filters,
            'stats'      => $stats,
            'annees'     => $annees,
            'classes'    => $classes,
            'statuts'    => \App\Modules\Finance\Models\FactureModel::STATUTS,
            'canCreate'  => $this->policy->canCreate($user),
            'canManage'  => $this->policy->canEdit($user),
            'canAdmin'   => $this->policy->canGenererMasse($user),
        ]);
    }

    // GET /v2/finance/factures/create
    public function create(): void
    {
        $user = $this->currentUser();
        if (!$this->policy->canCreate($user)) {
            $this->redirect('/v2/finance/factures');
            return;
        }
        $eleves  = (new EleveModel())->findAllFiltered(['actif' => '1']);
        $classes = (new ClasseModel())->findForSelect();

        $this->render('Finance::factures/form', [
            'title'   => 'Nouvelle facture',
            'isEdit'  => false,
            'facture' => null,
            'errors'  => [],
            'old'     => [],
            'eleves'  => $eleves,
            'classes' => $classes,
        ]);
    }

    // POST /v2/finance/factures
    public function store(): void
    {
        $user = $this->currentUser();
        if (!$this->policy->canCreate($user)) {
            $this->json(['error' => 'Accès refusé.'], 403);
            return;
        }
        $this->verifyCsrf();

        $dto    = InvoiceDTO::fromRequest($_POST);
        $errors = $dto->validate();

        $lignesRaw = $_POST['lignes'] ?? [];
        $lignes    = [];
        foreach ($lignesRaw as $l) {
            $ligneDto = LigneFactureDTO::fromRequest($l);
            $ligneErrors = $ligneDto->validate();
            if ($ligneErrors) {
                $errors = array_merge($errors, $ligneErrors);
            } else {
                $lignes[] = $ligneDto;
            }
        }

        if ($errors) {
            $eleves  = (new EleveModel())->findAllFiltered(['actif' => '1']);
            $classes = (new ClasseModel())->findForSelect();
            $this->render('Finance::factures/form', [
                'title'   => 'Nouvelle facture',
                'isEdit'  => false,
                'facture' => null,
                'errors'  => $errors,
                'old'     => $_POST,
                'eleves'  => $eleves,
                'classes' => $classes,
            ]);
            return;
        }

        try {
            $factureId = $this->service->creerFacture($dto, $lignes, (int)$user['id']);
            \Core\Session::flash('success', 'Facture créée avec succès.');
            $this->redirect('/v2/finance/factures/' . $factureId);
        } catch (\Throwable $e) {
            $eleves  = (new EleveModel())->findAllFiltered(['actif' => '1']);
            $classes = (new ClasseModel())->findForSelect();
            $this->render('Finance::factures/form', [
                'title'   => 'Nouvelle facture',
                'isEdit'  => false,
                'facture' => null,
                'errors'  => ['global' => $e->getMessage()],
                'old'     => $_POST,
                'eleves'  => $eleves,
                'classes' => $classes,
            ]);
        }
    }

    // GET /v2/finance/factures/{id}
    public function show(int $id): void
    {
        $user    = $this->currentUser();
        $this->requirePermission('finance.factures.view');

        $facture  = $this->repo->findWithDetails($id);
        if (!$facture) {
            \Core\Session::flash('error', 'Facture introuvable.');
            $this->redirect('/v2/finance/factures');
            return;
        }

        $this->render('Finance::factures/show', [
            'title'        => 'Facture ' . $facture->numero,
            'facture'      => $facture,
            'lignes'       => $this->repo->getLignes($id),
            'remises'      => $this->repo->getRemises($id),
            'penalites'    => $this->repo->getPenalites($id),
            'echeancier'   => $this->repo->getEcheancier($id),
            'avoirs'       => $this->repo->getAvoirs($id),
            'statuts'      => \App\Modules\Finance\Models\FactureModel::STATUTS,
            'canEdit'      => $this->policy->canEdit($user),
            'canEmettre'   => $this->policy->canEmettre($user),
            'canAnnuler'   => $this->policy->canAnnuler($user),
            'canArchiver'  => $this->policy->canArchiver($user),
            'canSupprimer' => $this->policy->canSupprimer($user),
            'canRemise'    => $this->policy->canAppliquerRemise($user),
            'canPrint'     => $this->policy->canPrint($user),
        ]);
    }

    // GET /v2/finance/factures/{id}/edit
    public function edit(int $id): void
    {
        $user = $this->currentUser();
        if (!$this->policy->canEdit($user)) {
            $this->redirect('/v2/finance/factures/' . $id);
            return;
        }
        $facture = $this->repo->findWithDetails($id);
        if (!$facture || $facture->statut !== 'brouillon') {
            \Core\Session::flash('error', 'Cette facture n\'est pas modifiable.');
            $this->redirect('/v2/finance/factures/' . $id);
            return;
        }
        $eleves  = (new EleveModel())->findAllFiltered(['actif' => '1']);
        $classes = (new ClasseModel())->findForSelect();
        $this->render('Finance::factures/form', [
            'title'   => 'Modifier la facture',
            'isEdit'  => true,
            'facture' => $facture,
            'lignes'  => $this->repo->getLignes($id),
            'errors'  => [],
            'old'     => [],
            'eleves'  => $eleves,
            'classes' => $classes,
        ]);
    }

    // POST /v2/finance/factures/{id}
    public function update(int $id): void
    {
        $user = $this->currentUser();
        if (!$this->policy->canEdit($user)) {
            $this->redirect('/v2/finance/factures/' . $id);
            return;
        }
        $this->verifyCsrf();
        try {
            $this->service->modifier($id, $_POST, (int)$user['id']);
            \Core\Session::flash('success', 'Facture mise à jour.');
        } catch (\Throwable $e) {
            \Core\Session::flash('error', $e->getMessage());
        }
        $this->redirect('/v2/finance/factures/' . $id);
    }

    // POST /v2/finance/factures/{id}/emettre
    public function emettre(int $id): void
    {
        $user = $this->currentUser();
        if (!$this->policy->canEmettre($user)) {
            \Core\Session::flash('error', 'Accès refusé.');
            $this->redirect('/v2/finance/factures/' . $id);
            return;
        }
        $this->verifyCsrf();
        try {
            $this->service->emettre($id, (int)$user['id']);
            \Core\Session::flash('success', 'Facture émise avec succès.');
        } catch (\Throwable $e) {
            \Core\Session::flash('error', $e->getMessage());
        }
        $this->redirect('/v2/finance/factures/' . $id);
    }

    // POST /v2/finance/factures/{id}/annuler
    public function annuler(int $id): void
    {
        $user = $this->currentUser();
        if (!$this->policy->canAnnuler($user)) {
            \Core\Session::flash('error', 'Accès refusé.');
            $this->redirect('/v2/finance/factures/' . $id);
            return;
        }
        $this->verifyCsrf();
        $motif = trim($_POST['motif'] ?? '');
        if (empty($motif)) {
            \Core\Session::flash('error', 'Un motif d\'annulation est requis.');
            $this->redirect('/v2/finance/factures/' . $id);
            return;
        }
        try {
            $this->service->annuler($id, $motif, (int)$user['id']);
            \Core\Session::flash('success', 'Facture annulée.');
        } catch (\Throwable $e) {
            \Core\Session::flash('error', $e->getMessage());
        }
        $this->redirect('/v2/finance/factures/' . $id);
    }

    // POST /v2/finance/factures/{id}/archiver
    public function archiver(int $id): void
    {
        $user = $this->currentUser();
        if (!$this->policy->canArchiver($user)) {
            \Core\Session::flash('error', 'Accès refusé.');
            $this->redirect('/v2/finance/factures/' . $id);
            return;
        }
        $this->verifyCsrf();
        try {
            $this->service->archiver($id, (int)$user['id']);
            \Core\Session::flash('success', 'Facture archivée.');
        } catch (\Throwable $e) {
            \Core\Session::flash('error', $e->getMessage());
        }
        $this->redirect('/v2/finance/factures');
    }

    // POST /v2/finance/factures/{id}/delete
    public function destroy(int $id): void
    {
        $user = $this->currentUser();
        if (!$this->policy->canSupprimer($user)) {
            \Core\Session::flash('error', 'Accès refusé.');
            $this->redirect('/v2/finance/factures/' . $id);
            return;
        }
        $this->verifyCsrf();
        try {
            $this->service->supprimer($id, (int)$user['id']);
            \Core\Session::flash('success', 'Facture supprimée.');
            $this->redirect('/v2/finance/factures');
        } catch (\Throwable $e) {
            \Core\Session::flash('error', $e->getMessage());
            $this->redirect('/v2/finance/factures/' . $id);
        }
    }

    // POST /v2/finance/factures/{id}/remise
    public function storeRemise(int $id): void
    {
        $user = $this->currentUser();
        if (!$this->policy->canAppliquerRemise($user)) {
            \Core\Session::flash('error', 'Accès refusé.');
            $this->redirect('/v2/finance/factures/' . $id);
            return;
        }
        $this->verifyCsrf();
        try {
            $dto = RemiseDTO::fromRequest($_POST);
            $this->service->appliquerRemise($id, $dto, (int)$user['id']);
            \Core\Session::flash('success', 'Remise appliquée.');
        } catch (\Throwable $e) {
            \Core\Session::flash('error', $e->getMessage());
        }
        $this->redirect('/v2/finance/factures/' . $id);
    }

    // POST /v2/finance/factures/{id}/ligne
    public function storeLigne(int $id): void
    {
        $user = $this->currentUser();
        if (!$this->policy->canEdit($user)) {
            $this->redirect('/v2/finance/factures/' . $id);
            return;
        }
        $this->verifyCsrf();
        try {
            $dto = LigneFactureDTO::fromRequest($_POST);
            $this->service->ajouterLigne($id, $dto, (int)$user['id']);
            \Core\Session::flash('success', 'Ligne ajoutée.');
        } catch (\Throwable $e) {
            \Core\Session::flash('error', $e->getMessage());
        }
        $this->redirect('/v2/finance/factures/' . $id . '/edit');
    }

    // POST /v2/finance/factures/{id}/ligne/{ligneId}/delete
    public function destroyLigne(int $id, int $ligneId): void
    {
        $user = $this->currentUser();
        if (!$this->policy->canEdit($user)) {
            $this->redirect('/v2/finance/factures/' . $id);
            return;
        }
        $this->verifyCsrf();
        try {
            $this->service->supprimerLigne($id, $ligneId, (int)$user['id']);
            \Core\Session::flash('success', 'Ligne supprimée.');
        } catch (\Throwable $e) {
            \Core\Session::flash('error', $e->getMessage());
        }
        $this->redirect('/v2/finance/factures/' . $id . '/edit');
    }

    // POST /v2/finance/factures/{id}/echeancier
    public function storeEcheancier(int $id): void
    {
        $user = $this->currentUser();
        if (!$this->policy->canEdit($user)) {
            $this->redirect('/v2/finance/factures/' . $id);
            return;
        }
        $this->verifyCsrf();
        $echeances = $_POST['echeances'] ?? [];
        try {
            $this->service->creerEcheancier($id, $echeances, (int)$user['id']);
            \Core\Session::flash('success', 'Échéancier créé.');
        } catch (\Throwable $e) {
            \Core\Session::flash('error', $e->getMessage());
        }
        $this->redirect('/v2/finance/factures/' . $id);
    }

    // GET /v2/finance/factures/{id}/print
    public function print(int $id): void
    {
        $user = $this->currentUser();
        if (!$this->policy->canPrint($user)) {
            $this->redirect('/v2/finance/factures/' . $id);
            return;
        }
        $facture = $this->repo->findWithDetails($id);
        if (!$facture) {
            $this->redirect('/v2/finance/factures');
            return;
        }
        $branding = \Core\Tenant\BrandingService::forCurrentRequest();
        $this->render('Finance::factures/print', [
            'facture'    => $facture,
            'lignes'     => $this->repo->getLignes($id),
            'remises'    => $this->repo->getRemises($id),
            'echeancier' => $this->repo->getEcheancier($id),
            'branding'   => $branding,
            'devise'     => \Core\Tenant\SettingsService::make()->get($branding->etablissementId, 'finance', 'devise_defaut', 'XOF'),
        ], 'none');
    }

    // GET /v2/finance/factures/generer
    public function genererForm(): void
    {
        $user = $this->currentUser();
        if (!$this->policy->canGenererMasse($user)) {
            $this->redirect('/v2/finance/factures');
            return;
        }
        $classes = (new ClasseModel())->findForSelect();
        $stmt    = $this->repo->getPdo()->query(
            "SELECT * FROM `finance_frais_types` WHERE `statut` = 'actif' ORDER BY `nom`"
        );
        $fraisTypes = $stmt->fetchAll(\PDO::FETCH_OBJ);

        $this->render('Finance::factures/generer', [
            'title'       => 'Génération en masse',
            'classes'     => $classes,
            'fraisTypes'  => $fraisTypes,
            'niveaux'     => array_keys(ClasseModel::NIVEAUX),
            'errors'      => [],
        ]);
    }

    // POST /v2/finance/factures/generer
    public function generer(): void
    {
        $user = $this->currentUser();
        if (!$this->policy->canGenererMasse($user)) {
            \Core\Session::flash('error', 'Accès refusé.');
            $this->redirect('/v2/finance/factures');
            return;
        }
        $this->verifyCsrf();

        $annee       = trim($_POST['annee_scolaire'] ?? '');
        $fraisIds    = array_map('intval', (array)($_POST['frais_type_ids'] ?? []));
        $classeId    = !empty($_POST['classe_id'])  ? (int)$_POST['classe_id']  : null;
        $niveau      = !empty($_POST['niveau'])      ? trim($_POST['niveau'])    : null;

        if (empty($annee) || empty($fraisIds)) {
            \Core\Session::flash('error', 'Année scolaire et types de frais sont requis.');
            $this->redirect('/v2/finance/factures/generer');
            return;
        }

        try {
            if ($classeId) {
                $resultats = $this->service->genererPourClasse($classeId, $fraisIds, $annee, (int)$user['id']);
            } else {
                $resultats = $this->service->genererMasse($annee, $niveau, $fraisIds, (int)$user['id']);
            }
            $msg = "{$resultats['crees']} facture(s) créée(s), {$resultats['ignores']} ignorée(s).";
            if (!empty($resultats['erreurs'])) {
                $msg .= ' ' . count($resultats['erreurs']) . ' erreur(s).';
                \Core\Session::flash('warning', $msg);
            } else {
                \Core\Session::flash('success', $msg);
            }
        } catch (\Throwable $e) {
            \Core\Session::flash('error', $e->getMessage());
        }
        $this->redirect('/v2/finance/factures');
    }
}

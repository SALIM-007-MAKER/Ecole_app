<?php

namespace App\Modules\Finance\Controllers;

use Core\Controller;
use App\Modules\Finance\DTO\PaymentDTO;
use App\Modules\Finance\DTO\PaymentFiltersDTO;
use App\Modules\Finance\DTO\RefundDTO;
use App\Modules\Finance\Policies\PaymentPolicy;
use App\Modules\Finance\Repositories\PaymentRepository;
use App\Modules\Finance\Repositories\InvoiceRepository;
use App\Modules\Finance\Services\PaymentService;
use App\Shared\Auth\EleveScopeTrait;

class PaiementController extends Controller
{
    use EleveScopeTrait;

    private PaymentService    $service;
    private PaymentRepository $repo;
    private InvoiceRepository $invoiceRepo;
    private PaymentPolicy     $policy;

    public function __construct()
    {
        parent::__construct();
        $this->service     = new PaymentService();
        $this->repo        = new PaymentRepository();
        $this->invoiceRepo = new InvoiceRepository();
        $this->policy      = new PaymentPolicy();
    }

    // GET /v2/finance/paiements
    public function index(): void
    {
        $user = $this->currentUser();
        $this->requirePermission('finance.paiements.view');

        $filters  = PaymentFiltersDTO::fromRequest($_GET);
        $result   = $this->repo->paginate($filters);
        $stats    = $this->repo->statsGlobal($filters->dateDebut, $filters->dateFin);
        $parMode  = $this->repo->statsParMode($filters->dateDebut, $filters->dateFin);
        $modes    = $this->repo->getModesPaiement();
        $annees   = $this->invoiceRepo->getAnneesActives();

        $this->render('Finance::paiements/index', [
            'title'     => 'Paiements',
            'result'    => $result,
            'filters'   => $filters,
            'stats'     => $stats,
            'par_mode'  => $parMode,
            'modes'     => $modes,
            'annees'    => $annees,
            'statuts'   => \App\Modules\Finance\Models\PaiementModel::STATUTS,
            'canCreate' => $this->policy->canCreate($user),
            'canAdmin'  => $this->policy->canAnnuler($user),
        ]);
    }

    // GET /v2/finance/paiements/create?facture_id=X
    public function create(): void
    {
        $user = $this->currentUser();
        if (!$this->policy->canCreate($user)) {
            $this->redirect('/v2/finance/paiements');
            return;
        }

        $factureId = (int)($_GET['facture_id'] ?? 0);
        $facture   = $factureId ? $this->invoiceRepo->findWithDetails($factureId) : null;

        if (!$facture || !in_array($facture->statut, ['emise','partiellement_payee','en_retard'], true)) {
            \Core\Session::flash('error', 'Facture introuvable ou non payable.');
            $this->redirect('/v2/finance/factures');
            return;
        }

        $modes = $this->repo->getModesPaiement();
        $avoirs = $this->repo->getTropPercusByEleve((int)$facture->eleve_id);
        $avoirsDisponibles = $this->avoirsDisponibles((int)$facture->eleve_id);
        $echeancier = $this->invoiceRepo->getEcheancier($factureId);

        $this->render('Finance::paiements/form', [
            'title'             => 'Enregistrer un paiement',
            'facture'           => $facture,
            'modes'             => $modes,
            'avoirsDisponibles' => $avoirsDisponibles,
            'echeancier'        => $echeancier,
            'errors'            => [],
            'old'               => ['facture_id' => $factureId, 'date_paiement' => date('Y-m-d')],
        ]);
    }

    // POST /v2/finance/paiements
    public function store(): void
    {
        $user = $this->currentUser();
        if (!$this->policy->canCreate($user)) {
            $this->json(['error' => 'Accès refusé.'], 403);
            return;
        }
        $this->verifyCsrf();

        $dto    = PaymentDTO::fromRequest($_POST);
        $errors = $dto->validate();

        if ($errors) {
            $factureId = $dto->factureId;
            $facture   = $factureId ? $this->invoiceRepo->findWithDetails($factureId) : null;
            $modes     = $this->repo->getModesPaiement();
            $this->render('Finance::paiements/form', [
                'title'             => 'Enregistrer un paiement',
                'facture'           => $facture,
                'modes'             => $modes,
                'avoirsDisponibles' => $facture ? $this->avoirsDisponibles((int)$facture->eleve_id) : [],
                'echeancier'        => $facture ? $this->invoiceRepo->getEcheancier($factureId) : null,
                'errors'            => $errors,
                'old'               => $_POST,
            ]);
            return;
        }

        try {
            $paiementId = $this->service->enregistrer($dto, (int)$user['id']);
            \Core\Session::flash('success', 'Paiement enregistré avec succès.');
            $this->redirect('/v2/finance/paiements/' . $paiementId);
        } catch (\Throwable $e) {
            $factureId  = $dto->factureId;
            $facture    = $factureId ? $this->invoiceRepo->findWithDetails($factureId) : null;
            $modes      = $this->repo->getModesPaiement();
            $this->render('Finance::paiements/form', [
                'title'             => 'Enregistrer un paiement',
                'facture'           => $facture,
                'modes'             => $modes,
                'avoirsDisponibles' => $facture ? $this->avoirsDisponibles((int)$facture->eleve_id) : [],
                'echeancier'        => $facture ? $this->invoiceRepo->getEcheancier($factureId) : null,
                'errors'            => ['global' => $e->getMessage()],
                'old'               => $_POST,
            ]);
        }
    }

    // GET /v2/finance/paiements/{id}
    public function show(int $id): void
    {
        $user = $this->currentUser();
        $this->requirePermission('finance.paiements.view');

        $paiement = $this->repo->findWithDetails($id);
        if (!$paiement) {
            \Core\Session::flash('error', 'Paiement introuvable.');
            $this->redirect('/v2/finance/paiements');
            return;
        }

        $this->render('Finance::paiements/show', [
            'title'         => 'Paiement ' . $paiement->numero,
            'paiement'      => $paiement,
            'remboursements' => $this->repo->getRemboursements($id),
            'trop_percu'    => $this->repo->getTropPercu($id),
            'statuts'       => \App\Modules\Finance\Models\PaiementModel::STATUTS,
            'canValider'    => $this->policy->canValider($user),
            'canAnnuler'    => $this->policy->canAnnuler($user),
            'canRembourser' => $this->policy->canRembourser($user),
            'canPrintRecu'  => $this->policy->canPrintRecu($user),
            'canTropPercu'  => $this->policy->canTraiterTropPercu($user),
            'modesRemboursement' => \App\Modules\Finance\DTO\RefundDTO::MODES,
        ]);
    }

    // POST /v2/finance/paiements/{id}/valider
    public function valider(int $id): void
    {
        $user = $this->currentUser();
        if (!$this->policy->canValider($user)) {
            \Core\Session::flash('error', 'Accès refusé.');
            $this->redirect('/v2/finance/paiements/' . $id);
            return;
        }
        $this->verifyCsrf();
        try {
            $this->service->valider($id, (int)$user['id']);
            \Core\Session::flash('success', 'Paiement validé.');
        } catch (\Throwable $e) {
            \Core\Session::flash('error', $e->getMessage());
        }
        $this->redirect('/v2/finance/paiements/' . $id);
    }

    // POST /v2/finance/paiements/{id}/completer
    public function completer(int $id): void
    {
        $user = $this->currentUser();
        if (!$this->policy->canCompleter($user)) {
            \Core\Session::flash('error', 'Accès refusé.');
            $this->redirect('/v2/finance/paiements/' . $id);
            return;
        }
        $this->verifyCsrf();
        try {
            $this->service->completer($id, (int)$user['id']);
            \Core\Session::flash('success', 'Paiement complété. Le reçu a été généré.');
        } catch (\Throwable $e) {
            \Core\Session::flash('error', $e->getMessage());
        }
        $this->redirect('/v2/finance/paiements/' . $id);
    }

    // POST /v2/finance/paiements/{id}/annuler
    public function annuler(int $id): void
    {
        $user = $this->currentUser();
        if (!$this->policy->canAnnuler($user)) {
            \Core\Session::flash('error', 'Accès refusé.');
            $this->redirect('/v2/finance/paiements/' . $id);
            return;
        }
        $this->verifyCsrf();
        $motif = trim($_POST['motif'] ?? '');
        if (empty($motif)) {
            \Core\Session::flash('error', 'Un motif est requis.');
            $this->redirect('/v2/finance/paiements/' . $id);
            return;
        }
        try {
            $this->service->annuler($id, $motif, (int)$user['id']);
            \Core\Session::flash('success', 'Paiement annulé.');
        } catch (\Throwable $e) {
            \Core\Session::flash('error', $e->getMessage());
        }
        $this->redirect('/v2/finance/paiements/' . $id);
    }

    // POST /v2/finance/paiements/{id}/rembourser
    public function rembourser(int $id): void
    {
        $user = $this->currentUser();
        if (!$this->policy->canRembourser($user)) {
            \Core\Session::flash('error', 'Accès refusé.');
            $this->redirect('/v2/finance/paiements/' . $id);
            return;
        }
        $this->verifyCsrf();
        try {
            $dto = RefundDTO::fromRequest($_POST);
            $this->service->rembourser($id, $dto, (int)$user['id']);
            \Core\Session::flash('success', 'Remboursement enregistré.');
        } catch (\Throwable $e) {
            \Core\Session::flash('error', $e->getMessage());
        }
        $this->redirect('/v2/finance/paiements/' . $id);
    }

    // GET /v2/finance/paiements/{id}/recu
    public function recu(int $id): void
    {
        $user = $this->currentUser();
        if (!$this->policy->canPrintRecu($user)) {
            $this->redirect('/v2/finance/paiements/' . $id);
            return;
        }
        try {
            $recu = $this->service->getOuGenererRecu($id, (int)$user['id']);
        } catch (\Throwable $e) {
            \Core\Session::flash('error', $e->getMessage());
            $this->redirect('/v2/finance/paiements/' . $id);
            return;
        }
        $this->assertOwnEleve((int)$recu->eleve_id);
        $this->render('Finance::paiements/recu', [
            'title' => 'Reçu ' . $recu->numero,
            'recu'  => $recu,
        ]);
    }

    // GET /v2/finance/paiements/{id}/recu/print
    public function recuPrint(int $id): void
    {
        $user = $this->currentUser();
        if (!$this->policy->canPrintRecu($user)) {
            $this->redirect('/v2/finance/paiements');
            return;
        }
        try {
            $recu = $this->service->getOuGenererRecu($id, (int)$user['id']);
        } catch (\Throwable $e) {
            $this->redirect('/v2/finance/paiements/' . $id);
            return;
        }
        $this->assertOwnEleve((int)$recu->eleve_id);
        $branding = \Core\Tenant\BrandingService::forCurrentRequest();
        $this->render('Finance::paiements/recu_print', [
            'recu'     => $recu,
            'branding' => $branding,
            'devise'   => \Core\Tenant\SettingsService::make()->get($branding->etablissementId, 'finance', 'devise_defaut', 'XOF'),
        ], 'none');
    }

    // GET /v2/finance/factures/{factureId}/paiements
    public function parFacture(int $factureId): void
    {
        $this->requirePermission('finance.paiements.view');
        $facture    = $this->invoiceRepo->findWithDetails($factureId);
        if (!$facture) {
            \Core\Session::flash('error', 'Facture introuvable.');
            $this->redirect('/v2/finance/factures');
            return;
        }
        $paiements = $this->repo->getByFacture($factureId);
        $user      = $this->currentUser();
        $this->render('Finance::paiements/par_facture', [
            'title'     => 'Paiements — Facture ' . $facture->numero,
            'facture'   => $facture,
            'paiements' => $paiements,
            'statuts'   => \App\Modules\Finance\Models\PaiementModel::STATUTS,
            'canCreate' => $this->policy->canCreate($user),
        ]);
    }

    // POST /v2/finance/paiements/{id}/trop-percu/{tpId}/{action}
    public function traiterTropPercu(int $id, int $tpId, string $action): void
    {
        $user = $this->currentUser();
        if (!$this->policy->canTraiterTropPercu($user)) {
            \Core\Session::flash('error', 'Accès refusé.');
            $this->redirect('/v2/finance/paiements/' . $id);
            return;
        }
        $this->verifyCsrf();
        try {
            $this->service->traiterTropPercu($tpId, $action, (int)$user['id']);
            \Core\Session::flash('success', 'Trop-perçu traité.');
        } catch (\Throwable $e) {
            \Core\Session::flash('error', $e->getMessage());
        }
        $this->redirect('/v2/finance/paiements/' . $id);
    }

    // ----------------------------------------------------------------
    // Private helper
    // ----------------------------------------------------------------
    private function avoirsDisponibles(int $eleveId): array
    {
        $stmt = $this->repo->getPdo()->prepare(
            "SELECT * FROM `finance_avoirs`
             WHERE `eleve_id` = ? AND `statut` = 'emis'
             ORDER BY `created_at` DESC"
        );
        $stmt->execute([$eleveId]);
        return $stmt->fetchAll(\PDO::FETCH_OBJ);
    }
}

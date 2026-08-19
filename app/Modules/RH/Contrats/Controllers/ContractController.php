<?php

declare(strict_types=1);

namespace App\Modules\RH\Contrats\Controllers;

use App\Modules\RH\Contrats\DTO\ContractDTO;
use App\Modules\RH\Contrats\DTO\ContractFiltersDTO;
use App\Modules\RH\Contrats\DTO\AvenantDTO;
use App\Modules\RH\Contrats\Models\ContractModel;
use App\Modules\RH\Contrats\Policies\ContractPolicy;
use App\Modules\RH\Contrats\Services\ContractService;
use Core\Controller;
use Core\Session;

class ContractController extends Controller
{
    private ContractService $service;
    private ContractPolicy  $policy;

    public function __construct()
    {
        parent::__construct();
        $this->service = new ContractService();
        $this->policy  = new ContractPolicy();

        // Traiter les expirations automatiques à chaque requête RH/Contrats
        try { $this->service->traiterExpirations(); } catch (\Throwable) {}
    }

    // ── Liste ─────────────────────────────────────────────────────────────────

    public function index(): void
    {
        $this->requirePermission('contract.view');

        $filters = ContractFiltersDTO::fromRequest($_GET);
        $result  = $this->service->paginate($filters);
        $stats   = $this->service->statistiques();
        $refs    = $this->service->referentiels();

        $this->render('RH::contrats/index', [
            'contrats'     => $result['items'],
            'pagination'   => $result,
            'filters'      => $filters,
            'stats'        => $stats,
            'departements' => $refs['departements'],
            'model'        => ContractModel::class,
            'canCreate'    => $this->policy->canCreate($this->currentUser()),
            'canExport'    => $this->policy->canExport($this->currentUser()),
        ]);
    }

    // ── Alertes d'échéance ────────────────────────────────────────────────────

    public function echeances(): void
    {
        $this->requirePermission('contract.view');

        $jours    = max(1, min(365, (int)($_GET['jours'] ?? 90)));
        $contrats = $this->service->echeances($jours);

        $this->render('RH::contrats/echeances', [
            'contrats' => $contrats,
            'jours'    => $jours,
            'model'    => ContractModel::class,
            'canRenew' => $this->policy->canRenew($this->currentUser()),
        ]);
    }

    // ── Détail ────────────────────────────────────────────────────────────────

    public function show(int $id): void
    {
        $this->requirePermission('contract.view');

        try {
            $contrat  = $this->service->findById($id);
            $avenants = $this->service->findAvenants($id);
        } catch (\RuntimeException $e) {
            Session::flash('error', $e->getMessage());
            $this->redirect('/v2/rh/contrats');
            return;
        }

        $this->render('RH::contrats/show', [
            'contrat'   => $contrat,
            'avenants'  => $avenants,
            'model'     => ContractModel::class,
            'canUpdate' => $this->policy->canUpdate($this->currentUser()),
            'canRenew'  => $this->policy->canRenew($this->currentUser()),
            'canTerminate' => $this->policy->canTerminate($this->currentUser()),
            'canArchive'   => $this->policy->canArchive($this->currentUser()),
        ]);
    }

    // ── Création ──────────────────────────────────────────────────────────────

    public function create(): void
    {
        $this->requirePermission('contract.create');

        $refs = $this->service->referentiels();

        $this->render('RH::contrats/create', [
            'refs'   => $refs,
            'model'  => ContractModel::class,
            'old'    => [],
            'errors' => [],
        ]);
    }

    public function store(): void
    {
        $this->requirePermission('contract.create');
        $this->verifyCsrf();

        $dto    = ContractDTO::fromRequest($_POST);
        $errors = $dto->validate();

        if ($errors !== []) {
            $refs = $this->service->referentiels();
            $this->render('RH::contrats/create', [
                'refs'   => $refs,
                'model'  => ContractModel::class,
                'old'    => $_POST,
                'errors' => $errors,
            ]);
            return;
        }

        try {
            $id = $this->service->creer($dto, (int)$this->currentUser()['id']);
            Session::flash('success', 'Contrat créé avec succès.');
            $this->redirect('/v2/rh/contrats/' . $id);
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            $refs = $this->service->referentiels();
            $this->render('RH::contrats/create', [
                'refs'   => $refs,
                'model'  => ContractModel::class,
                'old'    => $_POST,
                'errors' => ['global' => $e->getMessage()],
            ]);
        }
    }

    // ── Édition ───────────────────────────────────────────────────────────────

    public function edit(int $id): void
    {
        $this->requirePermission('contract.update');

        try {
            $contrat = $this->service->findById($id);
            $refs    = $this->service->referentiels();
        } catch (\RuntimeException $e) {
            Session::flash('error', $e->getMessage());
            $this->redirect('/v2/rh/contrats');
            return;
        }

        $this->render('RH::contrats/edit', [
            'contrat' => $contrat,
            'refs'    => $refs,
            'model'   => ContractModel::class,
            'old'     => [],
            'errors'  => [],
        ]);
    }

    public function update(int $id): void
    {
        $this->requirePermission('contract.update');
        $this->verifyCsrf();

        $dto    = ContractDTO::fromRequest($_POST);
        $errors = $dto->validate();

        if ($errors !== []) {
            try {
                $contrat = $this->service->findById($id);
                $refs    = $this->service->referentiels();
            } catch (\RuntimeException $e) {
                $contrat = []; $refs = [];
            }
            $this->render('RH::contrats/edit', [
                'contrat' => $contrat,
                'refs'    => $refs,
                'model'   => ContractModel::class,
                'old'     => $_POST,
                'errors'  => $errors,
            ]);
            return;
        }

        try {
            $this->service->modifier($id, $dto, (int)$this->currentUser()['id']);
            Session::flash('success', 'Contrat mis à jour.');
            $this->redirect('/v2/rh/contrats/' . $id);
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            Session::flash('error', $e->getMessage());
            $this->redirect('/v2/rh/contrats/' . $id . '/edit');
        }
    }

    // ── Avenant ───────────────────────────────────────────────────────────────

    public function storeAvenant(int $id): void
    {
        $this->requirePermission('contract.update');
        $this->verifyCsrf();

        $dto = AvenantDTO::fromRequest($_POST);

        try {
            $this->service->ajouterAvenant($id, $dto, (int)$this->currentUser()['id']);
            Session::flash('success', 'Avenant ajouté.');
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect('/v2/rh/contrats/' . $id);
    }

    // ── Renouvellement ────────────────────────────────────────────────────────

    public function renouveler(int $id): void
    {
        $this->requirePermission('contract.renew');
        $this->verifyCsrf();

        $nouvelleDateDebut = trim($_POST['nouvelle_date_debut'] ?? '');
        $nouvelleDateFin   = ($_POST['nouvelle_date_fin'] ?? '') !== '' ? trim($_POST['nouvelle_date_fin']) : null;

        try {
            $nouveauId = $this->service->renouveler($id, $nouvelleDateDebut, $nouvelleDateFin, (int)$this->currentUser()['id']);
            Session::flash('success', 'Contrat renouvelé. Nouveau contrat créé.');
            $this->redirect('/v2/rh/contrats/' . $nouveauId);
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            Session::flash('error', $e->getMessage());
            $this->redirect('/v2/rh/contrats/' . $id);
        }
    }

    // ── Résiliation ───────────────────────────────────────────────────────────

    public function resilier(int $id): void
    {
        $this->requirePermission('contract.terminate');
        $this->verifyCsrf();

        $motif = trim($_POST['motif'] ?? '');

        try {
            $this->service->resilier($id, $motif, (int)$this->currentUser()['id']);
            Session::flash('success', 'Contrat résilié.');
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect('/v2/rh/contrats/' . $id);
    }

    // ── Suspension / Réactivation ─────────────────────────────────────────────

    public function suspendre(int $id): void
    {
        $this->requirePermission('contract.update');
        $this->verifyCsrf();

        try {
            $this->service->suspendre($id, (int)$this->currentUser()['id']);
            Session::flash('success', 'Contrat suspendu.');
        } catch (\RuntimeException $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect('/v2/rh/contrats/' . $id);
    }

    public function reactiver(int $id): void
    {
        $this->requirePermission('contract.update');
        $this->verifyCsrf();

        try {
            $this->service->reactiver($id, (int)$this->currentUser()['id']);
            Session::flash('success', 'Contrat réactivé.');
        } catch (\RuntimeException $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect('/v2/rh/contrats/' . $id);
    }

    // ── Activation brouillon ──────────────────────────────────────────────────

    public function activer(int $id): void
    {
        $this->requirePermission('contract.update');
        $this->verifyCsrf();

        try {
            $this->service->activer($id, (int)$this->currentUser()['id']);
            Session::flash('success', 'Contrat activé.');
        } catch (\RuntimeException $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect('/v2/rh/contrats/' . $id);
    }

    // ── Archivage ─────────────────────────────────────────────────────────────

    public function archive(int $id): void
    {
        $this->requirePermission('contract.archive');
        $this->verifyCsrf();

        try {
            $this->service->archiver($id, (int)$this->currentUser()['id']);
            Session::flash('success', 'Contrat archivé.');
        } catch (\RuntimeException $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect('/v2/rh/contrats');
    }

    // ── Statistiques ──────────────────────────────────────────────────────────

    public function statistiques(): void
    {
        $this->requirePermission('contract.view');

        $stats = $this->service->statistiques();

        $this->render('RH::contrats/statistiques', [
            'stats' => $stats,
            'model' => ContractModel::class,
        ]);
    }

    // ── Export ────────────────────────────────────────────────────────────────

    public function export(): void
    {
        $this->requirePermission('contract.export');

        $filters = ContractFiltersDTO::fromRequest($_GET);
        $csv     = $this->service->exporterCsv($filters);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="contrats_' . date('Ymd') . '.csv"');
        echo $csv;
        exit;
    }
}

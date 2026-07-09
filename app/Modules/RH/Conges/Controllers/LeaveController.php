<?php

declare(strict_types=1);

namespace App\Modules\RH\Conges\Controllers;

use App\Modules\RH\Conges\DTO\LeaveDTO;
use App\Modules\RH\Conges\DTO\LeaveFiltersDTO;
use App\Modules\RH\Conges\DTO\LeaveSoldeDTO;
use App\Modules\RH\Conges\Models\LeaveModel;
use App\Modules\RH\Conges\Policies\LeavePolicy;
use App\Modules\RH\Conges\Services\LeaveService;
use Core\Controller;
use Core\Session;

class LeaveController extends Controller
{
    private LeaveService $service;
    private LeavePolicy  $policy;

    public function __construct()
    {
        parent::__construct();
        $this->service = new LeaveService();
        $this->policy  = new LeavePolicy();
    }

    private function currentUser(): array { return Session::getUser() ?? []; }
    private function userId(): int        { return (int)($this->currentUser()['id'] ?? 0); }
    private function userName(): string   {
        $u = $this->currentUser();
        return trim(($u['prenom'] ?? '') . ' ' . ($u['nom'] ?? '')) ?: 'Système';
    }

    // ── Liste ─────────────────────────────────────────────────────────────────

    public function index(): void
    {
        $this->requirePermission('leave.view');

        $filters = LeaveFiltersDTO::fromRequest($_GET);
        $result  = $this->service->paginate($filters);
        $stats   = $this->service->statistiques();
        $refs    = $this->service->referentiels();
        $user    = $this->currentUser();

        $this->render('RH::conges/index', [
            'conges'       => $result['items'],
            'pagination'   => $result,
            'filters'      => $filters,
            'stats'        => $stats,
            'departements' => $refs['departements'],
            'typesConges'  => $refs['typesConges'],
            'model'        => LeaveModel::class,
            'canCreate'    => $this->policy->canCreate($user),
            'canExport'    => $this->policy->canExport($user),
            'canApprove'   => $this->policy->canApprove($user),
        ]);
    }

    // ── File d'approbation ────────────────────────────────────────────────────

    public function validation(): void
    {
        $this->requirePermission('leave.approve');

        $enAttente = $this->service->findEnAttente();
        $user      = $this->currentUser();

        $this->render('RH::conges/validation', [
            'conges'     => $enAttente,
            'model'      => LeaveModel::class,
            'canApprove' => $this->policy->canApprove($user),
            'canReject'  => $this->policy->canReject($user),
        ]);
    }

    // ── Gestion des soldes ────────────────────────────────────────────────────

    public function soldes(): void
    {
        $this->requirePermission('leave.view');

        $annee    = (int)($_GET['annee'] ?? date('Y'));
        $employeId = ($_GET['employe_id'] ?? '') !== '' ? (int)$_GET['employe_id'] : null;
        $refs      = $this->service->referentiels();

        $this->render('RH::conges/soldes', [
            'soldes'      => $this->service->getSoldes($employeId, $annee),
            'employes'    => $refs['employes'],
            'typesConges' => $refs['typesConges'],
            'model'       => LeaveModel::class,
            'annee'       => $annee,
            'employeId'   => $employeId,
            'canUpdate'   => $this->policy->canUpdate($this->currentUser()),
        ]);
    }

    public function storeSolde(): void
    {
        $this->requirePermission('leave.update');
        $this->verifyCsrf();

        $dto = LeaveSoldeDTO::fromRequest($_POST);

        try {
            $this->service->definirSolde($dto, $this->userId());
            Session::flash('success', 'Solde mis à jour avec succès.');
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            Session::flash('error', $e->getMessage());
        }

        $this->redirect('/v2/rh/conges/soldes');
    }

    // ── Détail ────────────────────────────────────────────────────────────────

    public function show(int $id): void
    {
        $this->requirePermission('leave.view');

        try {
            $conge      = $this->service->findById($id);
            $historique = $this->service->findHistorique($id);
        } catch (\RuntimeException $e) {
            Session::flash('error', $e->getMessage());
            $this->redirect('/v2/rh/conges');
            return;
        }

        $user = $this->currentUser();

        $this->render('RH::conges/show', [
            'conge'      => $conge,
            'historique' => $historique,
            'model'      => LeaveModel::class,
            'canUpdate'  => $this->policy->canUpdate($user),
            'canApprove' => $this->policy->canApprove($user),
            'canReject'  => $this->policy->canReject($user),
            'canCancel'  => $this->policy->canCancel($user),
        ]);
    }

    // ── Création ──────────────────────────────────────────────────────────────

    public function create(): void
    {
        $this->requirePermission('leave.create');

        $employeId = ($_GET['employe_id'] ?? '') !== '' ? (int)$_GET['employe_id'] : null;
        $refs      = $this->service->referentiels($employeId);

        $this->render('RH::conges/create', [
            'refs'         => $refs,
            'model'        => LeaveModel::class,
            'preEmployeId' => $employeId,
            'today'        => date('Y-m-d'),
            'old'          => [],
            'errors'       => [],
        ]);
    }

    public function store(): void
    {
        $this->requirePermission('leave.create');
        $this->verifyCsrf();

        $dto    = LeaveDTO::fromRequest($_POST);
        $errors = $dto->validate();

        if ($errors !== []) {
            $refs = $this->service->referentiels($dto->employeId ?: null);
            $this->render('RH::conges/create', [
                'refs'         => $refs,
                'model'        => LeaveModel::class,
                'preEmployeId' => $dto->employeId ?: null,
                'today'        => date('Y-m-d'),
                'old'          => $_POST,
                'errors'       => $errors,
            ]);
            return;
        }

        try {
            $id = $this->service->creer($dto, $this->userId(), $this->userName());
            Session::flash('success', 'Demande de congé créée en brouillon. Soumettez-la pour approbation.');
            $this->redirect('/v2/rh/conges/' . $id);
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            $refs = $this->service->referentiels($dto->employeId ?: null);
            $this->render('RH::conges/create', [
                'refs'         => $refs,
                'model'        => LeaveModel::class,
                'preEmployeId' => $dto->employeId ?: null,
                'today'        => date('Y-m-d'),
                'old'          => $_POST,
                'errors'       => ['global' => $e->getMessage()],
            ]);
        }
    }

    // ── Édition ───────────────────────────────────────────────────────────────

    public function edit(int $id): void
    {
        $this->requirePermission('leave.update');

        try {
            $conge = $this->service->findById($id);
            $refs  = $this->service->referentiels((int)$conge['employe_id']);
        } catch (\RuntimeException $e) {
            Session::flash('error', $e->getMessage());
            $this->redirect('/v2/rh/conges');
            return;
        }

        if ($conge['statut'] !== 'brouillon') {
            Session::flash('error', 'Seules les demandes en brouillon peuvent être modifiées.');
            $this->redirect('/v2/rh/conges/' . $id);
            return;
        }

        $this->render('RH::conges/edit', [
            'conge'  => $conge,
            'refs'   => $refs,
            'model'  => LeaveModel::class,
            'old'    => [],
            'errors' => [],
        ]);
    }

    public function update(int $id): void
    {
        $this->requirePermission('leave.update');
        $this->verifyCsrf();

        $dto    = LeaveDTO::fromRequest($_POST);
        $errors = $dto->validate();

        if ($errors !== []) {
            try {
                $conge = $this->service->findById($id);
                $refs  = $this->service->referentiels((int)$conge['employe_id']);
            } catch (\RuntimeException) {
                $conge = []; $refs = [];
            }
            $this->render('RH::conges/edit', [
                'conge'  => $conge,
                'refs'   => $refs,
                'model'  => LeaveModel::class,
                'old'    => $_POST,
                'errors' => $errors,
            ]);
            return;
        }

        try {
            $this->service->modifier($id, $dto, $this->userId(), $this->userName());
            Session::flash('success', 'Demande mise à jour.');
            $this->redirect('/v2/rh/conges/' . $id);
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            Session::flash('error', $e->getMessage());
            $this->redirect('/v2/rh/conges/' . $id . '/edit');
        }
    }

    // ── Transitions ───────────────────────────────────────────────────────────

    public function soumettre(int $id): void
    {
        $this->requirePermission('leave.create');
        $this->verifyCsrf();

        try {
            $this->service->soumettre($id, $this->userId(), $this->userName());
            Session::flash('success', 'Demande soumise. En attente d\'approbation.');
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect('/v2/rh/conges/' . $id);
    }

    public function approuver(int $id): void
    {
        $this->requirePermission('leave.approve');
        $this->verifyCsrf();

        try {
            $this->service->approuver($id, $this->userId(), $this->userName());
            Session::flash('success', 'Congé approuvé.');
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect('/v2/rh/conges/' . $id);
    }

    public function rejeter(int $id): void
    {
        $this->requirePermission('leave.reject');
        $this->verifyCsrf();

        $motif = trim($_POST['motif_rejet'] ?? '');

        try {
            $this->service->rejeter($id, $motif, $this->userId(), $this->userName());
            Session::flash('success', 'Demande rejetée.');
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect('/v2/rh/conges/' . $id);
    }

    public function annuler(int $id): void
    {
        $this->requirePermission('leave.cancel');
        $this->verifyCsrf();

        $motif = trim($_POST['motif_annulation'] ?? '');

        try {
            $this->service->annuler($id, $motif, $this->userId(), $this->userName());
            Session::flash('success', 'Demande annulée.');
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect('/v2/rh/conges/' . $id);
    }

    public function demarrer(int $id): void
    {
        $this->requirePermission('leave.update');
        $this->verifyCsrf();

        try {
            $this->service->demarrer($id, $this->userId(), $this->userName());
            Session::flash('success', 'Congé marqué comme en cours.');
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect('/v2/rh/conges/' . $id);
    }

    public function terminer(int $id): void
    {
        $this->requirePermission('leave.update');
        $this->verifyCsrf();

        $dateRetour  = trim($_POST['date_retour_effectif'] ?? '') ?: null;
        $commentaire = trim($_POST['commentaire_retour']   ?? '') ?: null;

        try {
            $this->service->terminer($id, $dateRetour, $commentaire, $this->userId(), $this->userName());
            Session::flash('success', 'Congé terminé. Solde mis à jour.');
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect('/v2/rh/conges/' . $id);
    }

    // ── Export ────────────────────────────────────────────────────────────────

    public function export(): void
    {
        $this->requirePermission('leave.export');

        $filters = LeaveFiltersDTO::fromRequest($_GET);
        $csv     = $this->service->exporterCsv($filters);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="conges_personnel_' . date('Ymd') . '.csv"');
        echo $csv;
        exit;
    }
}

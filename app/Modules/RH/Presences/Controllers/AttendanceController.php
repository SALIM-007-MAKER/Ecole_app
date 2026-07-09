<?php

declare(strict_types=1);

namespace App\Modules\RH\Presences\Controllers;

use App\Modules\RH\Presences\DTO\AttendanceDTO;
use App\Modules\RH\Presences\DTO\AttendanceFiltersDTO;
use App\Modules\RH\Presences\DTO\RegularisationDTO;
use App\Modules\RH\Presences\Models\AttendanceModel;
use App\Modules\RH\Presences\Policies\AttendancePolicy;
use App\Modules\RH\Presences\Services\AttendanceService;
use Core\Controller;
use Core\Session;

class AttendanceController extends Controller
{
    private AttendanceService $service;
    private AttendancePolicy  $policy;

    public function __construct()
    {
        parent::__construct();
        $this->service = new AttendanceService();
        $this->policy  = new AttendancePolicy();
    }

    // ── Liste ─────────────────────────────────────────────────────────────────

    public function index(): void
    {
        $this->requirePermission('rh.presence.view');

        $filters = AttendanceFiltersDTO::fromRequest($_GET);
        $result  = $this->service->paginate($filters);
        $stats   = $this->service->statistiques();
        $refs    = $this->service->referentiels();
        $user    = Session::getUser() ?? [];

        $this->render('RH::presences/index', [
            'presences'    => $result['items'],
            'pagination'   => $result,
            'filters'      => $filters,
            'stats'        => $stats,
            'departements' => $refs['departements'],
            'model'        => AttendanceModel::class,
            'canCreate'    => $this->policy->canCreate($user),
            'canExport'    => $this->policy->canExport($user),
            'canValidate'  => $this->policy->canValidate($user),
        ]);
    }

    // ── File de validation ────────────────────────────────────────────────────

    public function validation(): void
    {
        $this->requirePermission('rh.presence.validate');

        $enAttente = $this->service->findEnAttente();
        $user      = Session::getUser() ?? [];

        $this->render('RH::presences/validation', [
            'presences'  => $enAttente,
            'model'      => AttendanceModel::class,
            'canValidate'=> $this->policy->canValidate($user),
        ]);
    }

    // ── Détail ────────────────────────────────────────────────────────────────

    public function show(int $id): void
    {
        $this->requirePermission('rh.presence.view');

        try {
            $presence       = $this->service->findById($id);
            $regularisations= $this->service->findRegularisations($id);
        } catch (\RuntimeException $e) {
            Session::flash('error', $e->getMessage());
            $this->redirect('/v2/rh/presences');
            return;
        }

        $user = Session::getUser() ?? [];

        $this->render('RH::presences/show', [
            'presence'        => $presence,
            'regularisations' => $regularisations,
            'model'           => AttendanceModel::class,
            'canUpdate'       => $this->policy->canUpdate($user),
            'canValidate'     => $this->policy->canValidate($user),
        ]);
    }

    // ── Création ──────────────────────────────────────────────────────────────

    public function create(): void
    {
        $this->requirePermission('rh.presence.create');

        $employeId = ($_GET['employe_id'] ?? '') !== '' ? (int)$_GET['employe_id'] : null;
        $refs      = $this->service->referentiels($employeId);

        $this->render('RH::presences/create', [
            'refs'         => $refs,
            'model'        => AttendanceModel::class,
            'preEmployeId' => $employeId,
            'today'        => date('Y-m-d'),
            'old'          => [],
            'errors'       => [],
        ]);
    }

    public function store(): void
    {
        $this->requirePermission('rh.presence.create');
        $this->verifyCsrf();

        $dto    = AttendanceDTO::fromRequest($_POST);
        $errors = $dto->validate();

        if ($errors !== []) {
            $refs = $this->service->referentiels($dto->employeId ?: null);
            $this->render('RH::presences/create', [
                'refs'         => $refs,
                'model'        => AttendanceModel::class,
                'preEmployeId' => $dto->employeId ?: null,
                'today'        => date('Y-m-d'),
                'old'          => $_POST,
                'errors'       => $errors,
            ]);
            return;
        }

        try {
            $currentUser = Session::getUser() ?? [];
            $userId      = (int)($currentUser['id']     ?? 0);
            $userName    = trim(($currentUser['prenom'] ?? '') . ' ' . ($currentUser['nom'] ?? '')) ?: 'Système';

            $id = $this->service->creer($dto, $userId, $userName);
            Session::flash('success', 'Pointage enregistré avec succès.');
            $this->redirect('/v2/rh/presences/' . $id);
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            $refs = $this->service->referentiels($dto->employeId ?: null);
            $this->render('RH::presences/create', [
                'refs'         => $refs,
                'model'        => AttendanceModel::class,
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
        $this->requirePermission('rh.presence.update');

        try {
            $presence = $this->service->findById($id);
            $refs     = $this->service->referentiels((int)$presence['employe_id']);
        } catch (\RuntimeException $e) {
            Session::flash('error', $e->getMessage());
            $this->redirect('/v2/rh/presences');
            return;
        }

        $this->render('RH::presences/edit', [
            'presence' => $presence,
            'refs'     => $refs,
            'model'    => AttendanceModel::class,
            'old'      => [],
            'errors'   => [],
        ]);
    }

    public function update(int $id): void
    {
        $this->requirePermission('rh.presence.update');
        $this->verifyCsrf();

        $dto    = AttendanceDTO::fromRequest($_POST);
        $errors = $dto->validate();

        if ($errors !== []) {
            try {
                $presence = $this->service->findById($id);
                $refs     = $this->service->referentiels((int)$presence['employe_id']);
            } catch (\RuntimeException) {
                $presence = []; $refs = [];
            }
            $this->render('RH::presences/edit', [
                'presence' => $presence,
                'refs'     => $refs,
                'model'    => AttendanceModel::class,
                'old'      => $_POST,
                'errors'   => $errors,
            ]);
            return;
        }

        try {
            $currentUser = Session::getUser() ?? [];
            $userId      = (int)($currentUser['id'] ?? 0);
            $this->service->modifier($id, $dto, $userId);
            Session::flash('success', 'Pointage mis à jour.');
            $this->redirect('/v2/rh/presences/' . $id);
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            Session::flash('error', $e->getMessage());
            $this->redirect('/v2/rh/presences/' . $id . '/edit');
        }
    }

    // ── Validation ────────────────────────────────────────────────────────────

    public function valider(int $id): void
    {
        $this->requirePermission('rh.presence.validate');
        $this->verifyCsrf();

        $decision   = trim($_POST['decision']    ?? '');
        $motifRejet = trim($_POST['motif_rejet'] ?? '') ?: null;

        try {
            $currentUser = Session::getUser() ?? [];
            $userId      = (int)($currentUser['id']     ?? 0);
            $userName    = trim(($currentUser['prenom'] ?? '') . ' ' . ($currentUser['nom'] ?? '')) ?: 'Système';

            $this->service->valider($id, $decision, $motifRejet, $userId, $userName);
            Session::flash('success', $decision === 'valide' ? 'Pointage validé.' : 'Pointage rejeté.');
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect('/v2/rh/presences/' . $id);
    }

    // ── Justification ─────────────────────────────────────────────────────────

    public function justifier(int $id): void
    {
        $this->requirePermission('rh.presence.update');
        $this->verifyCsrf();

        $justification = trim($_POST['justification'] ?? '');

        try {
            $currentUser = Session::getUser() ?? [];
            $userId      = (int)($currentUser['id']     ?? 0);
            $userName    = trim(($currentUser['prenom'] ?? '') . ' ' . ($currentUser['nom'] ?? '')) ?: 'Système';

            $this->service->justifier($id, $justification, $userId, $userName);
            Session::flash('success', 'Justification enregistrée.');
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect('/v2/rh/presences/' . $id);
    }

    // ── Régularisation ────────────────────────────────────────────────────────

    public function regulariser(int $id): void
    {
        $this->requirePermission('rh.presence.update');
        $this->verifyCsrf();

        $dto = RegularisationDTO::fromRequest($_POST);

        try {
            $currentUser = Session::getUser() ?? [];
            $userId      = (int)($currentUser['id']     ?? 0);
            $userName    = trim(($currentUser['prenom'] ?? '') . ' ' . ($currentUser['nom'] ?? '')) ?: 'Système';

            $this->service->regulariser($id, $dto, $userId, $userName);
            Session::flash('success', 'Régularisation enregistrée. Le pointage est repassé en attente de validation.');
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect('/v2/rh/presences/' . $id);
    }

    // ── Archivage ─────────────────────────────────────────────────────────────

    public function archive(int $id): void
    {
        $this->requirePermission('rh.presence.update');
        $this->verifyCsrf();

        $motif = trim($_POST['motif'] ?? 'Archivage manuel');

        try {
            $currentUser = Session::getUser() ?? [];
            $userId      = (int)($currentUser['id'] ?? 0);
            $this->service->archiver($id, $motif, $userId);
            Session::flash('success', 'Pointage archivé.');
        } catch (\RuntimeException $e) {
            Session::flash('error', $e->getMessage());
            $this->redirect('/v2/rh/presences/' . $id);
            return;
        }
        $this->redirect('/v2/rh/presences');
    }

    // ── Restauration ──────────────────────────────────────────────────────────

    public function restore(int $id): void
    {
        $this->requirePermission('rh.presence.update');
        $this->verifyCsrf();

        try {
            $currentUser = Session::getUser() ?? [];
            $userId      = (int)($currentUser['id'] ?? 0);
            $this->service->restaurer($id, $userId);
            Session::flash('success', 'Pointage restauré.');
        } catch (\RuntimeException $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect('/v2/rh/presences/' . $id);
    }

    // ── Statistiques ──────────────────────────────────────────────────────────

    public function statistiques(): void
    {
        $this->requirePermission('rh.presence.view');

        $stats = $this->service->statistiques();

        $this->render('RH::presences/statistiques', [
            'stats' => $stats,
            'model' => AttendanceModel::class,
        ]);
    }

    // ── Export ────────────────────────────────────────────────────────────────

    public function export(): void
    {
        $this->requirePermission('rh.presence.export');

        $filters = AttendanceFiltersDTO::fromRequest($_GET);
        $csv     = $this->service->exporterCsv($filters);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="presences_personnel_' . date('Ymd') . '.csv"');
        echo $csv;
        exit;
    }
}
